<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\Mail\EmailProvider;
use App\Models\Profit\ProfitSetting;
use App\Services\Profit\OrderSync;
use App\Services\Profit\ProfitEngine;
use App\Services\Profit\WeeklySummary;
use App\Support\EmailSettings;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Phase 3, stage 3: staff access (G28), Sales insights (G14) and the weekly email (G16). */
class ProfitStaffAndInsightsTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        foreach (['profit', 'sales_insights'] as $flag) {
            FeatureFlag::create(['key' => $flag, 'mode' => 'all', 'enabled' => true]);
        }
        Features::forget();
    }

    private function store(string $plan = 'growth')
    {
        $store = $this->installedStore(['plan' => $plan, 'timezone' => 'UTC', 'scopes' => config('shopify.scopes').',read_inventory,read_all_orders']);
        ProfitSetting::for($store->id)->forceFill(['backfilled_at' => now(), 'fees' => ['percent' => 0, 'fixed' => 0, 'countries' => []]])->save();
        $m = fn ($v) => ['shop_money' => ['amount' => (string) $v]];
        foreach ([[1, 3, false, 'US'], [2, 2, true, 'GB']] as [$id, $daysAgo, $returning, $cc]) {
            app(OrderSync::class)->fromWebhook($store, ['id' => $id, 'processed_at' => now()->subDays($daysAgo)->toIso8601String(), 'customer' => ['id' => $id, 'orders_count' => $returning ? 3 : 1],
                'shipping_address' => ['country_code' => $cc], 'total_shipping_price_set' => $m(0), 'total_tax_set' => $m(0), 'total_price_set' => $m(50),
                'line_items' => [['id' => $id, 'product_id' => 10, 'variant_id' => 101, 'title' => 'Serum', 'quantity' => 1, 'price_set' => $m(50), 'discount_allocations' => []]]]);
        }
        app(ProfitEngine::class)->recalculateAll($store);

        return $store;
    }

    public function test_staff_see_profit_only_when_an_owner_allows_it_within_the_plan(): void
    {
        $store = $this->store('starter'); // 2 people may see profit
        $owner = $this->member($store, 'owner');
        $leo = $this->member($store, 'staff');
        $ravi = $this->member($store, 'admin');

        $this->page('/app/profit', $leo)->assertStatus(403)->assertJsonPath('component', 'private')->assertJsonPath('props.module', 'Profit and costs');
        $this->page('/app/cro', $leo)->assertOk();
        $this->page('/app/settings/users', $owner)->assertJsonPath('props.staffAccess.seats', [1, 2]);

        // Two people besides the owner is over Starter's 2.
        $this->send('/app/settings/users/access', ['access' => [$leo->id => ['profit' => true], $ravi->id => ['profit' => true]]], $owner)->assertStatus(422);
        $this->send('/app/settings/users/access', ['access' => [$leo->id => ['profit' => true, 'offers' => false]]], $owner)->assertOk();
        $this->page('/app/profit', $leo)->assertOk()->assertJsonPath('component', 'profit/dashboard');
        $this->page('/app/cro', $leo)->assertStatus(403)->assertJsonPath('props.module', 'Offers and widgets');
    }

    public function test_sales_insights_read_the_same_numbers_as_profit(): void
    {
        $store = $this->store();
        $owner = $this->member($store, 'owner');
        $this->page('/app/insights/sales', $owner)->assertOk()
            ->assertJsonPath('props.now.net_sales', 100)->assertJsonPath('props.now.orders', 2)->assertJsonPath('props.now.aov', 50)
            ->assertJsonPath('props.now.returning', 50)->assertJsonPath('props.countries.0.share', 50)
            ->assertJsonPath('shared.nav.groups.0.items.0.label', 'Sales');
    }

    public function test_the_monday_email_goes_once_to_people_who_see_profit(): void
    {
        EmailSettings::save(['enabled' => true, 'strategy' => 'priority', 'from_email' => 'hello@orderorbit.space', 'from_name' => 'Growvia', 'reply_to' => '', 'team_email' => 'team@orderorbit.space']);
        EmailProvider::create(['name' => 'Resend', 'driver' => 'resend', 'credentials' => ['api_key' => 're_1'], 'priority' => 10, 'is_active' => true]);
        Http::fake(fn (Request $r) => Http::response(['id' => 'rs_'.uniqid()]));
        $this->travelTo(now()->startOfWeek()->addHours(9)); // a Monday
        $store = $this->store();
        $owner = $this->member($store, 'owner');
        $this->member($store, 'staff'); // can't see profit: no email

        $email = app(WeeklySummary::class)->build($store->fresh());
        $this->assertStringContainsString('last week:', $email['subject']);
        $this->assertSame(1, app(WeeklySummary::class)->run());
        $this->assertSame(0, app(WeeklySummary::class)->run(), 'Once a week.');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'resend') && in_array($owner->email, (array) $r['to'], true));

        $this->get(URL::signedRoute('weekly.off', ['user' => $owner->id]))->assertOk();
        $this->assertFalse($owner->fresh()->weekly_email);
    }
}
