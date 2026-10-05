<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Store;
use App\Models\User;
use App\Services\Experiences\ExperienceManager;
use App\Services\Experiences\PublishException;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Experiences\TemplateLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class AdminPlansAndAccessTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        Http::fake(function (Request $request) {
            $query = $request['query'] ?? '';

            return match (true) {
                str_contains($query, 'currentAppInstallation { id }') => Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1']]]),
                str_contains($query, 'metafieldsSet') => Http::response(['data' => ['metafieldsSet' => ['userErrors' => []]]]),
                default => Http::response(['data' => []]),
            };
        });
        app(TemplateLibrary::class)->sync();
    }

    private function admin(string $role = 'super_admin', string $email = 'ava@orderorbit.space'): User
    {
        return User::forceCreate(['name' => 'Ava Team', 'email' => $email, 'password' => 'secret-password-123', 'is_admin' => true, 'admin_role' => $role]);
    }

    public function test_every_admin_page_renders(): void
    {
        $admin = $this->admin();
        $store = $this->installedStore();
        $plan = Plan::where('key', 'growth')->sole();
        foreach (['/admin', '/admin/stores', '/admin/stores?status=custom&plan=growth', "/admin/stores/{$store->id}", "/admin/stores/{$store->id}?tab=access", "/admin/stores/{$store->id}?tab=activity",
            '/admin/plans', '/admin/plans/new', "/admin/plans/{$plan->id}", '/admin/settings', '/admin/team', '/admin/tickets', '/admin/failures',
            '/admin/analytics', '/admin/templates', '/admin/flags', '/admin/audit', '/admin/account', '/admin/legal', '/admin/legal/new', '/admin/legal/1', '/admin/legal/1/versions/1'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_plans_are_editable_and_reach_the_app_and_pricing_page(): void
    {
        $admin = $this->admin();
        $plan = Plan::where('key', 'starter')->sole();
        $this->actingAs($admin)->post("/admin/plans/{$plan->id}", [
            'name' => 'Starter', 'shopify_name' => 'Starter', 'price' => '19.99', 'sales_limit' => '9000', 'trial_days' => 7,
            'modules' => ['bundles', 'offer_analytics', 'ab_testing'], 'limits' => ['bundles' => '3'], 'features' => "Line one\nLine two",
            'is_active' => '1', 'is_public' => '1',
        ])->assertRedirect('/admin/plans');

        $this->assertSame(19.99, config('shopify.billing.plans.starter.price'));
        $this->assertSame(['bundles', 'offer_analytics', 'ab_testing'], config('shopify.billing.plans.starter.includes'));
        $this->assertSame(3, config('shopify.billing.plans.starter.limits.bundles'));
        $store = $this->installedStore(['plan' => 'starter']);
        $this->assertTrue($store->planIncludes('ab_testing'));
        $this->assertFalse($store->planIncludes('countdown'), 'Unticked storefront modules are off.');
        $this->assertSame(3, $store->planLimit('bundles'));
        $this->assertSame(9000.0, $store->salesLimit());
        $this->get('/pricing')->assertOk();

        // The module grid: switch countdown on for Starter, A/B testing off.
        $grid = Plan::all()->mapWithKeys(fn ($p) => [$p->key => array_fill_keys($p->modules, '1')])->all();
        $grid['starter'] = ['bundles' => '1', 'offer_analytics' => '1', 'countdown' => '1'];
        $this->actingAs($admin)->post('/admin/plans/modules', ['grid' => $grid])->assertRedirect('/admin/plans');
        $this->assertSame(['bundles', 'countdown', 'offer_analytics'], Plan::where('key', 'starter')->value('modules'));
        $this->assertTrue($store->fresh()->planIncludes('countdown'));
        $this->assertFalse($store->fresh()->planIncludes('ab_testing'));

        // New plans; hidden plans stay off the pricing pages.
        $this->actingAs($admin)->post('/admin/plans', ['key' => 'agency', 'name' => 'Agency', 'shopify_name' => 'Agency', 'price' => 199, 'modules' => ['automation'], 'is_active' => '1'])->assertRedirect('/admin/plans');
        $this->assertSame('Agency', config('shopify.billing.plans.agency.name'));
        $this->assertArrayNotHasKey('agency', \App\Support\Plans::public());
    }

    public function test_store_access_overrides_plan_modules_and_limits(): void
    {
        $admin = $this->admin();
        $store = $this->installedStore(['plan' => null]);
        $this->assertFalse($store->hasPlanAccess());

        // A complimentary Scale plan, with bundles switched off and A/B testing kept on.
        $this->actingAs($admin)->post("/admin/stores/{$store->id}/access", [
            'plan' => 'scale', 'plan_until' => now()->addMonth()->toDateString(),
            'modules' => ['bundles' => 'off', 'ab_testing' => 'default'],
            'limits' => ['active_experiences' => ['mode' => 'value', 'value' => 2]], 'sales_mode' => 'unlimited', 'note' => 'Launch partner',
        ])->assertRedirect();
        $store->refresh();
        $this->assertSame('scale', $store->effectivePlan());
        $this->assertFalse($store->planIncludes('bundles'));
        $this->assertTrue($store->planIncludes('automation'));
        $this->assertSame(2, $store->planLimit('active_experiences'));
        $this->assertNull($store->salesLimit());

        $manager = app(ExperienceManager::class);
        $bundle = $manager->create($store, 'bundles', 'qb-classic', null);
        try {
            $manager->publish($bundle, null);
            $this->fail('Bundles are switched off for this store.');
        } catch (PublishException $e) {
            $this->assertSame('Bundles isn\'t included in your plan. Upgrade to publish it.', $e->getMessage());
        }

        // A module removed from a store leaves its storefront at once.
        $trust = $manager->create($store, 'trust', 'trust-row', null);
        $manager->publish($trust, null);
        $this->assertCount(1, app(StorefrontPublisher::class)->payload($store->fresh())['experiences']);
        $this->actingAs($admin)->post("/admin/stores/{$store->id}/access", ['plan' => 'scale', 'modules' => ['trust' => 'off']])->assertRedirect();
        $this->assertSame([], app(StorefrontPublisher::class)->payload($store->fresh())['experiences']);

        // A Free store gets one extra module without changing plan.
        $free = $this->installedStore(['shop_domain' => 'free.myshopify.com', 'plan' => 'free']);
        $this->assertFalse($free->planIncludes('ab_testing'));
        $this->actingAs($admin)->post("/admin/stores/{$free->id}/access", ['modules' => ['ab_testing' => 'on'], 'limits' => ['bundles' => ['mode' => 'unlimited']]])->assertRedirect();
        $free->refresh();
        $this->assertSame('free', $free->effectivePlan());
        $this->assertTrue($free->planIncludes('ab_testing'));
        $this->assertNull($free->planLimit('bundles'));
        $this->assertSame(1, $free->planLimit('free_gifts'), 'Other limits stay on the plan.');

        // Clearing everything goes back to the plan; an expired complimentary plan ends.
        $this->actingAs($admin)->post("/admin/stores/{$free->id}/access", [])->assertRedirect();
        $this->assertNull($free->fresh()->entitlements);
        $store->forceFill(['entitlements' => ['plan' => 'scale', 'plan_until' => now()->subDay()->toDateString()]])->save();
        $this->assertNull($store->fresh()->effectivePlan());
    }

    public function test_platform_settings_and_team_roles(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/settings', [
            'grace_days' => 5, 'warn_at' => 90, 'test_shops' => "qa.myshopify.com\nnot a domain", 'test_shop_plan' => 'growth', 'count_test_orders_for' => '',
        ])->assertRedirect();
        $this->assertSame(5, config('shopify.billing.grace_days'));
        $this->assertSame(0.9, config('shopify.billing.warn_at'));
        $this->assertSame(['qa.myshopify.com'], config('shopify.test_shops'));
        $this->assertSame('growth', $this->installedStore(['shop_domain' => 'qa.myshopify.com', 'plan' => null])->effectivePlan());

        // Team: a support agent sees stores and tickets, but not plans or access changes.
        $this->actingAs($admin)->post('/admin/team', ['name' => 'Sam', 'email' => 'sam@orderorbit.space', 'admin_role' => 'support'])->assertRedirect()->assertSessionHas('status');
        $sam = User::where('email', 'sam@orderorbit.space')->sole();
        $store = $this->installedStore(['shop_domain' => 's.myshopify.com']);
        $this->actingAs($sam)->get('/admin/stores')->assertOk();
        $this->actingAs($sam)->get('/admin/tickets')->assertOk();
        $this->actingAs($sam)->get('/admin/plans')->assertForbidden();
        $this->actingAs($sam)->get('/admin/settings')->assertForbidden();
        $this->actingAs($sam)->post("/admin/stores/{$store->id}/access", ['modules' => ['ab_testing' => 'on']])->assertForbidden();
        $this->assertNull($store->fresh()->entitlements);

        // Operations can change store access but not plans.
        $this->actingAs($admin)->post("/admin/team/{$sam->id}", ['admin_role' => 'operations'])->assertRedirect();
        $this->actingAs($sam->fresh())->post("/admin/stores/{$store->id}/access", ['modules' => ['ab_testing' => 'on']])->assertRedirect();
        $this->actingAs($sam->fresh())->get('/admin/plans')->assertOk();
        $this->actingAs($sam->fresh())->post('/admin/plans/modules', ['grid' => []])->assertForbidden();

        // Disabled team members are signed out and can't sign in.
        $this->actingAs($admin)->post("/admin/team/{$sam->id}", ['action' => 'disable'])->assertRedirect();
        $this->actingAs($sam->fresh())->get('/admin')->assertRedirect('/admin/login');
        auth()->logout();
        $this->post('/admin/login', ['email' => 'sam@orderorbit.space', 'password' => 'anything'])->assertSessionHasErrors('email');
        $this->actingAs($admin)->post("/admin/team/{$admin->id}", ['action' => 'disable'])->assertSessionHasErrors('team');
    }
}
