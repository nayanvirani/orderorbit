<?php

namespace Tests\Unit;

use App\Models\Store;
use App\Services\Usage;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_limits_follow_the_plan(): void
    {
        $usage = new Usage;
        $store = Store::create(['shop_domain' => 'u.myshopify.com']);

        $this->assertFalse($usage->allows($store, 'bundles'), 'No plan means nothing can be added.');

        // "MVP Pricing & Plan Accessibility (2026)": limits per plan (null = unlimited).
        $usage->register('bundles', fn () => 1);
        $store->plan = 'free';
        $this->assertFalse($usage->allows($store, 'bundles'), 'Free includes one live bundle.');
        $limits = fn (string $plan) => array_map(fn ($m) => tap($store, fn ($s) => $s->plan = $plan)->planLimit($m), ['active_experiences', 'bundles', 'free_gifts', 'shipping_bars', 'workflows', 'automation_executions']);
        $this->assertSame([4, 1, 1, 1, 1, 50], $limits('free'));
        $this->assertSame([15, 3, 2, 2, 5, 500], $limits('starter'));
        $store->plan = 'free';
        $this->assertSame([1, 1, 2, 0, 0, 0], array_map(fn ($m) => $store->planLimit($m), ['countdowns', 'sticky_atc', 'trust', 'cart_upsells', 'checkout_blocks', 'running_tests']));
        $this->assertSame(['bundles', 'bundles', 'free_gifts', 'shipping_bars', 'checkout_blocks', 'thank_you_blocks', 'account_blocks', 'post_purchase'], array_map(fn ($t) => Usage::meterFor($t), ['bundles', 'bogo', 'progressive-gifts', 'shipping-bar', 'checkout-image', 'ty-survey', 'account-reorder', 'post-purchase']));
        $this->assertSame([null, null, null, null, null, 3000], $limits('growth'));
        $this->assertSame([null, null, null, null, null, 10000], $limits('scale'));
        $store->plan = 'starter';
        $this->assertTrue($usage->allows($store, 'bundles'), 'Starter has two bundles.');

        // Features per plan.
        $includes = fn (string $plan, string $feature) => tap($store, fn ($s) => $s->plan = $plan)->planIncludes($feature);
        $this->assertTrue($includes('free', 'countdown') && $includes('free', 'sticky_atc') && $includes('free', 'trust') && $includes('free', 'offer_analytics') && $includes('free', 'automation'));
        $this->assertFalse($includes('free', 'quantity_breaks') || $includes('free', 'cart_upsells') || $includes('free', 'product_upsells'));
        $this->assertTrue($includes('starter', 'quantity_breaks') && $includes('starter', 'cart_upsells') && $includes('starter', 'product_upsells'));
        $this->assertFalse($includes('starter', 'ab_testing') || $includes('starter', 'checkout') || $includes('starter', 'automation_branching'));
        foreach (['checkout', 'thank_you', 'funnels_attribution', 'customer_journeys', 'automation_branching', 'automation_webhooks', 'ab_testing', 'ab_traffic_guardrails', 'personalization'] as $feature) {
            $this->assertTrue($includes('growth', $feature), "Growth includes {$feature}.");
        }
        $this->assertFalse($includes('growth', 'customer_accounts') || $includes('growth', 'personalization_advanced') || $includes('growth', 'ab_testing_advanced'));
        $this->assertTrue($includes('scale', 'customer_accounts') && $includes('scale', 'personalization_advanced') && $includes('scale', 'ab_testing_advanced'));

        // A module that's off has a limit of 0, whatever the number says.
        $store->plan = 'growth';
        $this->assertNull($store->planLimit('countdowns'));
        $store->entitlements = ['modules_off' => ['countdown']];
        $this->assertSame(0, $store->planLimit('countdowns'));
        $store->entitlements = null;

        // A child feature needs its parent: Quantity breaks without Bundles is off.
        $store->entitlements = ['modules_off' => ['bundles']];
        $this->assertFalse($includes('scale', 'quantity_breaks'));
        $store->entitlements = null;

        $store->plan = 'growth';
        $usage->increment($store, 'automation_executions', 3000);
        $this->assertFalse($usage->allows($store, 'automation_executions'));
    }

    public function test_permission_matrix(): void
    {
        $this->assertTrue(Permissions::allows('owner', 'manage_billing'));
        $this->assertFalse(Permissions::allows('admin', 'manage_billing'));
        $this->assertTrue(Permissions::allows('admin', 'manage_users'));
        $this->assertFalse(Permissions::allows('staff', 'manage_settings'));
        $this->assertTrue(Permissions::allows('staff', 'manage_experiences'));
        $this->assertFalse(Permissions::allows(null, 'view_dashboard'));
        $this->assertSame(['admin', 'staff'], Permissions::assignableBy('admin'));
    }
}
