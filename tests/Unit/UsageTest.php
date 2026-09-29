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

        $store->plan = 'starter';
        $usage->register('bundles', fn () => 1);
        $this->assertFalse($usage->allows($store, 'bundles'));
        $this->assertTrue($usage->allows($store, 'workflows'));

        $store->plan = 'growth';
        $this->assertTrue($usage->allows($store, 'bundles'), 'Growth has unlimited bundles.');

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
