<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Subscription;
use App\Services\Shopify\Billing;
use App\Services\Usage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class ManagedPricingTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
    }

    private function shopifyReports(array $subscriptions): void
    {
        Http::fake(["{$this->shop}/admin/api/*" => Http::response(['data' => ['currentAppInstallation' => ['activeSubscriptions' => $subscriptions]]])]);
    }

    private function active(string $id, string $name, string $periodEnd = '+30 days'): array
    {
        return ['id' => $id, 'name' => $name, 'status' => 'ACTIVE', 'test' => true, 'trialDays' => 0, 'createdAt' => now()->toIso8601String(), 'currentPeriodEnd' => now()->modify($periodEnd)->toIso8601String()];
    }

    private function webhook(string $status, string $id, string $name, string $webhookId)
    {
        $body = json_encode(['app_subscription' => ['admin_graphql_api_id' => $id, 'name' => $name, 'status' => $status]]);

        return $this->call('POST', '/webhooks/shopify', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SHOPIFY_TOPIC' => 'app_subscriptions/update',
            'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $this->shop,
            'HTTP_X_SHOPIFY_WEBHOOK_ID' => $webhookId,
            'HTTP_X_SHOPIFY_HMAC_SHA256' => base64_encode(hash_hmac('sha256', $body, 'test-secret', true)),
        ], $body);
    }

    public function test_plan_names_map_case_insensitively(): void
    {
        $this->assertSame('growth', Billing::planKeyFromName('Growth'));
        $this->assertSame('growth', Billing::planKeyFromName('GROWTH'));
        $this->assertSame('scale', Billing::planKeyFromName('OrderOrbit Scale'));
        $this->assertNull(Billing::planKeyFromName('Free'));

        config(['shopify.billing.plans.starter.shopify_name' => 'Basic']);
        $this->assertSame('starter', Billing::planKeyFromName('basic'));
    }

    public function test_activation_webhook_records_plan_and_period_end(): void
    {
        $store = $this->installedStore();
        $this->shopifyReports([$this->active('gid://shopify/AppSubscription/1', 'Growth')]);

        $this->webhook('ACTIVE', 'gid://shopify/AppSubscription/1', 'Growth', 'w1')->assertNoContent();

        $store->refresh();
        $this->assertSame('growth', $store->plan);
        $this->assertNull($store->plan_expires_at);
        $this->assertNotNull(Subscription::first()->current_period_ends_at);
    }

    public function test_switching_plans_supersedes_the_old_subscription(): void
    {
        $store = $this->installedStore();
        $billing = app(Billing::class);

        $report = fn (array $subs) => ['data' => ['currentAppInstallation' => ['activeSubscriptions' => $subs]]];
        Http::fake(["{$this->shop}/admin/api/*" => Http::sequence()
            ->push($report([$this->active('gid://shopify/AppSubscription/1', 'Starter')]))
            ->push($report([$this->active('gid://shopify/AppSubscription/2', 'Scale')]))]);

        $billing->sync($store);
        $this->assertSame('starter', $store->fresh()->plan);
        $billing->sync($store->fresh());

        $this->assertSame('scale', $store->fresh()->plan);
        $this->assertSame('CANCELLED', Subscription::where('shopify_subscription_id', 'gid://shopify/AppSubscription/1')->value('status'));
    }

    public function test_cancellation_keeps_access_until_the_paid_period_ends(): void
    {
        $store = $this->installedStore();
        $this->shopifyReports([$this->active('gid://shopify/AppSubscription/1', 'Growth', '+10 days')]);
        app(Billing::class)->sync($store);

        $this->webhook('CANCELLED', 'gid://shopify/AppSubscription/1', 'Growth', 'w2')->assertNoContent();

        $store->refresh();
        $this->assertSame('growth', $store->plan);
        $this->assertTrue($store->plan_expires_at->isFuture());
        $this->assertTrue($store->hasPlanAccess());

        $this->travel(11)->days();
        $this->assertFalse($store->fresh()->hasPlanAccess());
        $this->assertFalse(app(Usage::class)->allows($store->fresh(), 'active_experiences'));
    }

    public function test_frozen_or_unknown_plans_give_no_access(): void
    {
        $store = $this->installedStore();

        $this->webhook('FROZEN', 'gid://shopify/AppSubscription/9', 'Growth', 'w3')->assertNoContent();
        $this->assertFalse($store->fresh()->hasPlanAccess());

        $this->shopifyReports([$this->active('gid://shopify/AppSubscription/10', 'Free')]);
        app(Billing::class)->sync($store->fresh());
        $this->assertNull($store->fresh()->plan);
    }

    public function test_test_shops_get_access_without_a_subscription(): void
    {
        config(['shopify.test_shops' => [$this->shop], 'shopify.test_shop_plan' => 'growth']);
        $store = $this->installedStore();

        $this->assertTrue($store->hasPlanAccess());
        $this->assertSame('growth', $store->effectivePlan());
        $this->assertNull($store->planLimit('bundles'));
        $this->assertFalse($this->installedStore(['shop_domain' => 'real.myshopify.com'])->hasPlanAccess());
    }

    public function test_dashboard_picks_up_a_plan_chosen_on_shopify_before_the_webhook(): void
    {
        $store = $this->installedStore();
        $owner = $this->member($store, 'owner');
        $this->shopifyReports([$this->active('gid://shopify/AppSubscription/5', 'Starter')]);

        $this->get('/app', $this->as($owner))->assertOk()->assertDontSee('Choose a plan to start publishing');
        $this->assertSame('starter', $store->fresh()->plan);
    }

    public function test_billing_page_links_to_shopifys_plan_picker(): void
    {
        config(['shopify.app_handle' => 'orderorbit-app']);
        $store = $this->installedStore();
        $owner = $this->member($store, 'owner');
        $staff = $this->member($store, 'staff');
        $this->shopifyReports([]);

        $this->get('/app/settings/billing', $this->as($owner))->assertOk()
            ->assertSee('https://admin.shopify.com/store/demo/charges/orderorbit-app/pricing_plans', false);
        $this->get('/app/settings/billing', $this->as($staff))->assertOk()
            ->assertSee('Only store owners can change the plan.')
            ->assertDontSee('charges/orderorbit-app/pricing_plans', false);
        $this->assertSame('https://admin.shopify.com/store/demo/charges/orderorbit-app/pricing_plans', Store::first()->pricingUrl());
    }
}
