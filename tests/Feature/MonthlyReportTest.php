<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Experience;
use App\Models\FeatureFlag;
use App\Models\Profit\ProductCost;
use App\Models\Profit\ProfitSetting;
use App\Models\Protect\ProtectAlert;
use App\Services\Insights\MonthlyReport;
use App\Services\Insights\MonthlyReportSender;
use App\Services\Mail\EmailSender;
use App\Services\Mail\OutgoingEmail;
use App\Services\Profit\OrderSync;
use App\Services\Profit\ProfitEngine;
use App\Services\Profit\WeeklySummary;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Monthly Business Report (G15) and the weekly email's Health Score and alerts (G16). */
class MonthlyReportTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    /** @var list<OutgoingEmail> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        foreach (['profit', 'protect', 'protect_center', 'monthly_report', 'sales_insights'] as $flag) {
            FeatureFlag::create(['key' => $flag, 'mode' => 'all', 'enabled' => true]);
        }
        Features::forget();
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(9, 0));
        $this->app->instance(EmailSender::class, new class($this->sent) extends EmailSender
        {
            public function __construct(private array &$box) {}

            public function send(OutgoingEmail $email, ?\App\Models\Mail\EmailProvider $only = null): \App\Services\Mail\SendResult
            {
                $this->box[] = $email;

                return \App\Services\Mail\SendResult::sent(new \App\Models\Mail\EmailProvider(['name' => 'test', 'driver' => 'log']), 'id');
            }
        });
    }

    private function store()
    {
        $store = $this->installedStore(['plan' => 'starter', 'timezone' => 'UTC', 'currency' => 'USD', 'scopes' => config('shopify.scopes').',read_inventory,read_all_orders']);
        ProfitSetting::for($store->id)->forceFill(['backfilled_at' => now(), 'fees' => ['percent' => 0, 'fixed' => 0, 'countries' => []]])->save();
        ProductCost::create(['store_id' => $store->id, 'variant_id' => 101, 'product_id' => 10, 'title' => 'Serum', 'price' => 40, 'shopify_cost' => 10, 'seen_at' => now(), 'product_status' => 'ACTIVE']);
        $m = fn ($v) => ['shop_money' => ['amount' => (string) $v]];
        // September: 2 orders; October: 3 orders.
        foreach ([[1, '2026-09-10'], [2, '2026-09-20'], [3, '2026-10-05'], [4, '2026-10-15'], [5, '2026-10-25']] as [$id, $day]) {
            app(OrderSync::class)->fromWebhook($store, ['id' => 9000 + $id, 'processed_at' => $day.'T12:00:00Z', 'total_shipping_price_set' => $m(0), 'total_tax_set' => $m(0), 'total_price_set' => $m(40),
                'line_items' => [['id' => $id, 'product_id' => 10, 'variant_id' => 101, 'title' => 'Serum', 'quantity' => 1, 'price_set' => $m(40), 'discount_allocations' => []]]]);
        }
        app(ProfitEngine::class)->recalculateAll($store);
        $offer = Experience::create(['store_id' => $store->id, 'type' => 'bundles', 'handle' => 'serum-bundle', 'name' => 'Serum bundle', 'template_key' => 'qb-classic', 'status' => 'published', 'draft_config' => []]);
        AnalyticsEvent::create(['store_id' => $store->id, 'event' => 'attributed', 'name' => 'attributed', 'experience_handle' => $offer->handle, 'value' => 25, 'occurred_at' => '2026-10-15 12:00:00']);
        ProtectAlert::create(['store_id' => $store->id, 'rule' => 'low_stock', 'subject' => 'v101', 'severity' => 'urgent', 'title' => 'Best seller running low: Serum', 'what' => '4 left.',
            'cost' => 70, 'cost_text' => 'About USD 70.00 of weekly sales at risk', 'fix' => 'Reorder by Friday.', 'status' => 'open', 'opened_at' => now(), 'last_seen_at' => now()]);

        return $store;
    }

    public function test_the_report_matches_the_month_and_compares_with_the_one_before(): void
    {
        $store = $this->store();
        $r = app(MonthlyReport::class)->build($store);
        $this->assertSame(['2026-10', 'October 2026', 'November'], [$r['month'], $r['label'], $r['next']]);
        $this->assertEquals(120, $r['kpis'][0]['value']);
        $this->assertSame('+50% vs September', $r['kpis'][0]['change']);
        $this->assertSame(3, $r['kpis'][3]['value']);
        $this->assertSame([['name' => 'Serum bundle', 'value' => 25.0]], $r['widgets']);
        $this->assertSame('Serum', $r['top'][0]['name']);
        $this->assertSame(['Best seller running low: Serum'], array_column($r['todo'], 'title'));
        $this->assertStringContainsString('Net sales rose 50% from September', $r['summary']);
        $this->assertStringStartsWith('%PDF', app(MonthlyReportSender::class)->pdf($r));

        $owner = $this->member($store, 'owner');
        $this->page('/app/insights/report?month=2026-09', $owner)->assertOk()->assertJsonPath('props.report.label', 'September 2026');
        $this->get('/app/insights/report/pdf?month=2026-10', $this->as($owner))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_it_is_emailed_once_on_the_1st_with_the_pdf(): void
    {
        $store = $this->store();
        $this->member($store, 'owner', ['email' => 'owner@example.com']);
        $this->assertSame(1, app(MonthlyReportSender::class)->run());
        $this->assertSame(0, app(MonthlyReportSender::class)->run(), 'Once per month.');
        $this->assertStringContainsString('October 2026 business report', $this->sent[0]->subject);
        $this->assertSame('growvia-report-2026-10.pdf', $this->sent[0]->attachments[0]['name']);
        $this->assertTrue(DB::table('lifecycle_emails')->where('kind', 'monthly:2026-10')->exists());

        // Mid-month, a store that just turned it on waits for the next 1st.
        DB::table('lifecycle_emails')->delete();
        $this->travelTo(now()->setDate(2026, 11, 15));
        $this->assertSame(0, app(MonthlyReportSender::class)->run());
    }

    public function test_the_weekly_email_has_the_health_score_and_top_alerts(): void
    {
        $store = $this->store();
        $email = app(WeeklySummary::class)->build($store);
        $this->assertStringContainsString('Health Score:', $email['parts']['intro']);
        $this->assertStringContainsString('1. Best seller running low: Serum: About USD 70.00 of weekly sales at risk.', $email['parts']['intro']);
        $this->assertSame('Open Action Center', $email['parts']['cta'][0]);
    }
}
