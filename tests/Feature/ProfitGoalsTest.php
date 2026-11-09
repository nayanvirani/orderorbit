<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\Profit\ProfitDaily;
use App\Models\Profit\ProfitSetting;
use App\Services\Mail\EmailSender;
use App\Services\Mail\OutgoingEmail;
use App\Services\Profit\ProfitGoals;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Profit goals and pacing (G6). */
class ProfitGoalsTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        foreach (['profit', 'profit_goals'] as $flag) {
            FeatureFlag::create(['key' => $flag, 'mode' => 'all', 'enabled' => true]);
        }
        Features::forget();
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(10, 0)); // 12 full days of October
        $this->app->instance(EmailSender::class, new class($this->sent) extends EmailSender
        {
            public function __construct(private array &$box) {}

            public function send(OutgoingEmail $email, ?\App\Models\Mail\EmailProvider $only = null): \App\Services\Mail\SendResult
            {
                $this->box[] = $email;

                return \App\Services\Mail\SendResult::sent(new \App\Models\Mail\EmailProvider(['name' => 't', 'driver' => 'log']), 'id');
            }
        });
    }

    private function store(string $plan = 'scale', string $domain = 'demo.myshopify.com')
    {
        $store = $this->installedStore(['shop_domain' => $domain, 'plan' => $plan, 'timezone' => 'UTC', 'currency' => 'USD', 'scopes' => config('shopify.scopes').',read_inventory,read_all_orders']);
        ProfitSetting::for($store->id)->forceFill(['backfilled_at' => now()])->save();
        // 12 days, 405 profit after ads a day (450 contribution − 45 ads).
        for ($d = 1; $d <= 12; $d++) {
            ProfitDaily::create(['store_id' => $store->id, 'day' => sprintf('2026-10-%02d', $d), 'orders' => 10, 'net_sales' => 1500, 'contribution' => 450, 'ad_spend' => 45]);
        }

        return $store;
    }

    public function test_pacing_projects_the_month_and_what_is_needed_per_day(): void
    {
        $store = $this->store();
        $owner = $this->member($store, 'owner', ['email' => 'owner@example.com']);
        $this->post('/app/profit/goals', ['type' => 'after_ads', 'amount' => 14000, 'alert' => 10], $this->as($owner))->assertRedirectContains('/app/profit/goals');

        $s = app(ProfitGoals::class)->status($store);
        $this->assertEquals(4860, $s['actual']);
        $this->assertEquals(12555, $s['projected']); // 405 × 31
        $this->assertEquals(10.3, $s['behind']);
        $this->assertEquals(481.05, $s['need']); // (14000 − 4860) / 19 days left, today included
        $this->assertSame([35, 39], [$s['goal_share'], $s['month_share']]);
        $this->assertEquals(4860, end($s['cumulative'])['total']);
        $this->page('/app/profit/goals', $owner)->assertOk()->assertJsonPath('props.status.elapsed', 12);

        // Warned once a month, at 10% behind or more.
        $this->assertTrue(app(ProfitGoals::class)->alert($store));
        $this->assertFalse(app(ProfitGoals::class)->alert($store), 'Once a month.');
        $this->assertStringContainsString('10.3% behind', $this->sent[0]->subject);
    }

    public function test_no_warning_early_in_the_month_or_when_on_track_and_scale_only(): void
    {
        $store = $this->store();
        ProfitSetting::for($store->id)->forceFill(['goal' => ['type' => 'after_ads', 'amount' => 12000, 'alert' => 10]])->save();
        $this->assertTrue(app(ProfitGoals::class)->status($store)['on_track']);
        $this->assertFalse(app(ProfitGoals::class)->alert($store));

        $this->travelTo(now()->setDate(2026, 10, 4));
        ProfitSetting::for($store->id)->forceFill(['goal' => ['type' => 'after_ads', 'amount' => 90000, 'alert' => 10]])->save();
        $this->assertFalse(app(ProfitGoals::class)->alert($store), 'Not before day 7.');

        $growth = $this->store('growth', 'growth.myshopify.com');
        $owner = $this->member($growth, 'owner');
        $this->post('/app/profit/goals', ['type' => 'after_ads', 'amount' => 100, 'alert' => 10], $this->as($owner));
        $this->assertNull(ProfitGoals::goal($growth), 'Goals are Scale and up.');
    }
}
