<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Services\Billing\SalesMeter;
use App\Services\Experiences\ExperienceManager;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Experiences\TemplateLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class SalesLimitTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    /** @var list<array{0: float, 1: string}> the store's orders in Shopify: [amount, currency] */
    private array $orders = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        config(['shopify.scopes' => 'read_products,write_discounts,read_orders']);
        app(TemplateLibrary::class)->sync();

        Http::fake(function (Request $request) {
            $query = $request['query'] ?? '';

            return match (true) {
                str_contains($query, 'currentTotalPriceSet') => Http::response(['data' => ['orders' => [
                    'nodes' => array_map(fn ($o) => ['currentTotalPriceSet' => ['shopMoney' => ['amount' => (string) $o[0], 'currencyCode' => $o[1]]]], $this->orders),
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                ]]]),
                // The billing page asks Shopify for the active subscription.
                str_contains($query, 'activeSubscriptions') => Http::response(['data' => ['currentAppInstallation' => ['app' => ['handle' => 'orderorbit'], 'activeSubscriptions' => [
                    ['id' => 'gid://shopify/AppSubscription/1', 'name' => 'Free', 'status' => 'ACTIVE', 'test' => false, 'trialDays' => 0, 'createdAt' => now()->toIso8601String(), 'currentPeriodEnd' => null],
                ]]]]),
                str_contains($query, 'currentAppInstallation { id }') => Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1']]]),
                str_contains($query, 'metafieldsSet') => Http::response(['data' => ['metafieldsSet' => ['userErrors' => []]]]),
                str_contains($request->url(), 'oauth/access_token') => Http::response(['access_token' => 'shpat_new', 'refresh_token' => 'shprt_new', 'expires_in' => 3600, 'scope' => 'read_products,write_discounts,read_orders']),
                str_contains($request->url(), 'frankfurter') => Http::response(['rates' => ['EUR' => 0.5]]), // 1 EUR = 2 USD
                default => Http::response(['data' => []]),
            };
        });
    }

    private function store(string $plan): Store
    {
        return $this->installedStore(['plan' => $plan, 'scopes' => 'read_products,write_discounts,read_orders']);
    }

    public function test_sales_are_counted_in_usd_and_the_store_moves_through_the_limit(): void
    {
        $store = $this->store('free');
        $meter = app(SalesMeter::class);
        $manager = app(ExperienceManager::class);
        $manager->publish($manager->create($store, 'trust', 'trust-row', null), null);

        // Under the limit.
        $this->orders = [[300, 'USD'], [100, 'EUR']]; // 300 + 200
        $meter->refresh($store);
        $this->assertEquals(500.0, $store->fresh()->sales_30d_usd);
        $this->assertSame('ok', $meter->status($store->fresh())['state']);

        // Close to it.
        $this->orders = [[850, 'USD']];
        $meter->refresh($store);
        $status = $meter->status($store->fresh());
        $this->assertSame(['near', 85], [$status['state'], $status['percent']]);

        // Over: the grace period starts, offers stay live.
        $this->orders = [[900, 'USD'], [400, 'USD']];
        $meter->refresh($store);
        $store->refresh();
        $status = $meter->status($store);
        $this->assertSame('over', $status['state']);
        $this->assertSame('starter', $status['next']['key'], 'The cheapest plan that fits is suggested.');
        $this->assertTrue($status['deadline']->isSameDay(now()->addDays(7)));
        $this->assertCount(1, app(StorefrontPublisher::class)->payload($store)['experiences']);

        // Grace period over: offers pause on the storefront; nothing is deleted.
        $this->travel(8)->days();
        $meter->refresh($store);
        $store->refresh();
        $this->assertTrue($store->offersSuspended());
        $this->assertSame('paused', $meter->status($store)['state']);
        $this->assertSame([], app(StorefrontPublisher::class)->payload($store)['experiences']);
        $this->assertSame('published', $store->experiences()->first()->status);

        // Upgrading to a plan that fits resumes everything straight away.
        $store->forceFill(['plan' => 'starter'])->save();
        $meter->evaluate($store);
        $store->refresh();
        $this->assertFalse($store->offersSuspended());
        $this->assertNull($store->over_limit_since);
        $this->assertCount(1, app(StorefrontPublisher::class)->payload($store)['experiences']);
    }

    public function test_dropping_back_under_the_limit_clears_the_grace_period(): void
    {
        $store = $this->store('starter');
        $meter = app(SalesMeter::class);

        $this->orders = [[9000, 'USD']];
        $meter->refresh($store);
        $this->assertNotNull($store->fresh()->over_limit_since);

        $this->orders = [[7000, 'USD']];
        $meter->refresh($store);
        $this->assertNull($store->fresh()->over_limit_since);
        $this->assertSame('near', $meter->status($store->fresh())['state']);
    }

    public function test_scale_and_test_shops_are_never_limited(): void
    {
        $meter = app(SalesMeter::class);
        $this->orders = [[250000, 'USD']];

        $scale = $this->store('scale');
        $meter->refresh($scale);
        $this->assertSame('ok', $meter->status($scale->fresh())['state']);
        $this->assertNull($meter->status($scale->fresh())['limit']);

        config(['shopify.test_shops' => ['dev.myshopify.com']]);
        $dev = $this->installedStore(['shop_domain' => 'dev.myshopify.com', 'plan' => 'free', 'scopes' => 'read_products,write_discounts,read_orders']);
        $meter->refresh($dev);
        $this->assertNull($dev->fresh()->over_limit_since);
    }

    public function test_the_app_shows_the_limit(): void
    {
        $store = $this->store('free');
        $owner = $this->member($store, 'owner');
        $store->forceFill(['sales_30d_usd' => 1300, 'sales_checked_at' => now(), 'over_limit_since' => now()])->save();

        $this->get('/app', $this->as($owner))->assertOk()->assertSee('passed your plan', false)->assertSee('Upgrade plan');
        $this->get('/app/settings/billing', $this->as($owner))->assertOk()
            ->assertSee('Store sales · last 30 days')->assertSee('$1,300')->assertSee('of $1,000 on your plan')
            ->assertSee('Up to $8,000 in monthly store sales')->assertSee('Free');

        $store->forceFill(['offers_suspended_at' => now()])->save();
        $this->get('/app/cro', $this->as($owner))->assertOk()->assertSee('Your offers are paused.');
    }
}
