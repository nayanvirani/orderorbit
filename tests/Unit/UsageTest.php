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

        // Free caps the revenue features at one live offer each; paid plans are unlimited.
        $usage->register('bundles', fn () => 1);
        $store->plan = 'free';
        $this->assertFalse($usage->allows($store, 'bundles'), 'Free includes one live bundle.');
        $this->assertTrue($usage->allows($store, 'active_experiences'), 'Countdowns, trust and the rest are not capped.');
        $this->assertSame([1, 1, 1, 1], array_map(fn ($m) => $store->planLimit($m), ['bundles', 'free_gifts', 'cart_upsells', 'preorders']));
        foreach (['starter', 'growth', 'scale'] as $plan) {
            $store->plan = $plan;
            $this->assertTrue($usage->allows($store, 'bundles'), "{$plan} has unlimited bundles.");
        }
        $this->assertSame([1000.0, 8000.0, 20000.0, null], array_map(fn ($plan) => tap($store, fn ($s) => $s->plan = $plan)->salesLimit(), ['free', 'starter', 'growth', 'scale']));

        // Higher plans unlock more.
        $includes = fn (string $plan, string $feature) => tap($store, fn ($s) => $s->plan = $plan)->planIncludes($feature);
        $this->assertFalse($includes('free', 'offer_analytics'));
        $this->assertTrue($includes('starter', 'offer_analytics'));
        $this->assertFalse($includes('starter', 'checkout'));
        $this->assertTrue($includes('growth', 'checkout') && $includes('growth', 'customer_accounts') && $includes('growth', 'ab_testing'));
        $this->assertFalse($includes('growth', 'automation'));
        $this->assertTrue($includes('scale', 'automation') && $includes('scale', 'personalization'));

        $store->plan = 'growth';

        $usage->increment($store, 'automation_executions', 10000);
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
