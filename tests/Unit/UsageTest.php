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

        // Every plan, including Free, has unlimited offers; plans differ by store sales.
        $usage->register('bundles', fn () => 50);
        foreach (['free', 'starter', 'growth', 'scale'] as $plan) {
            $store->plan = $plan;
            $this->assertTrue($usage->allows($store, 'bundles'), "{$plan} has unlimited bundles.");
        }
        $this->assertSame([1000.0, 8000.0, 20000.0, null], array_map(fn ($plan) => tap($store, fn ($s) => $s->plan = $plan)->salesLimit(), ['free', 'starter', 'growth', 'scale']));

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
