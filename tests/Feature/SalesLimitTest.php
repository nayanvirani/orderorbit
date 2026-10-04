<?php

namespace Tests\Feature;

use App\Models\SalesCycle;
use App\Models\Store;
use App\Models\StoreOrder;
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

    /** @var array<int, array> the store's orders in Shopify, by order id */
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
                    'nodes' => array_map(fn ($id, $o) => [
                        'id' => "gid://shopify/Order/{$id}", 'createdAt' => $o['at']->toIso8601String(), 'test' => $o['test'] ?? false,
                        'cancelledAt' => ($o['cancelled'] ?? false) ? now()->toIso8601String() : null,
                        'currentTotalPriceSet' => ['shopMoney' => ['amount' => (string) $o['amount'], 'currencyCode' => $o['currency'] ?? 'USD']],
                    ], array_keys($this->orders), $this->orders),
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

    private function store(string $plan, array $attributes = []): Store
    {
        return $this->installedStore($attributes + ['plan' => $plan, 'scopes' => 'read_products,write_discounts,read_orders']);
    }

    private function order(int $id, float $amount, array $extra = []): void
    {
        $this->orders[$id] = $extra + ['amount' => $amount, 'at' => now()];
    }

    private function webhook(string $topic, array $payload, string $id)
    {
        $body = json_encode($payload);

        return $this->call('POST', '/webhooks/shopify', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SHOPIFY_TOPIC' => $topic,
            'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $this->shop,
            'HTTP_X_SHOPIFY_WEBHOOK_ID' => $id,
            'HTTP_X_SHOPIFY_HMAC_SHA256' => base64_encode(hash_hmac('sha256', $body, 'test-secret', true)),
        ], $body);
    }

    public function test_orders_are_synced_into_a_table_and_counted_in_usd(): void
    {
        $store = $this->store('free');
        $meter = app(SalesMeter::class);
        $this->travel(1)->hours();

        $this->order(1, 300);
        $this->order(2, 100, ['currency' => 'EUR']);            // 200 USD
        $this->order(3, 5000, ['test' => true]);                // test orders don't count
        $this->order(4, 5000, ['cancelled' => true]);           // nor cancelled ones
        $meter->refresh($store);

        $this->assertSame(4, StoreOrder::count());
        $this->assertEquals(200.0, StoreOrder::where('shopify_order_id', '2')->value('amount_usd'));
        $this->assertEquals(500.0, $store->fresh()->cycle_sales_usd);
        $status = $meter->status($store->fresh());
        $this->assertSame(['ok', 2, ['count' => 1, 'usd' => 5000.0]], [$status['state'], $status['orders'], $status['test_orders']]);

        // The billing page says why test orders aren't in the total.
        $this->get('/app/settings/billing', $this->as($this->member($store, 'owner')))->assertOk()
            ->assertSee('2 orders counted this cycle')->assertSee('1 test order ($5,000) is not counted');

        // Our own stores can opt in to counting test orders, to try the limit end to end.
        config(['shopify.billing.count_test_orders_for' => [$store->shop_domain]]);
        $meter->recount($store->fresh());
        $this->assertEquals(5500.0, $store->fresh()->cycle_sales_usd);
        $this->assertNotNull($store->fresh()->over_limit_since);
        config(['shopify.billing.count_test_orders_for' => []]);
        $meter->recount($store->fresh());
        $this->assertNull($store->fresh()->over_limit_since);

        // Syncing again updates rows instead of duplicating them; a refund lowers the total.
        $this->order(1, 250);
        $meter->refresh($store->fresh());
        $this->assertSame(4, StoreOrder::count());
        $this->assertEquals(450.0, $store->fresh()->cycle_sales_usd);

        $this->order(5, 400);
        $meter->refresh($store->fresh());
        $status = $meter->status($store->fresh());
        $this->assertSame(['near', 85], [$status['state'], $status['percent']]);
        $this->assertTrue($status['cycle_end']->equalTo($store->fresh()->cycle_started_at->copy()->addDays(30)));
    }

    public function test_order_webhooks_keep_the_count_current(): void
    {
        $store = $this->store('free');
        $this->travel(1)->hours();

        $this->webhook('orders/create', ['id' => 11, 'current_total_price' => '700.00', 'currency' => 'USD', 'test' => false, 'created_at' => now()->toIso8601String(), 'line_items' => []], 'w1')->assertNoContent();
        $this->webhook('orders/create', ['id' => 12, 'current_total_price' => '250.00', 'currency' => 'USD', 'test' => false, 'created_at' => now()->toIso8601String(), 'line_items' => []], 'w2');
        $this->assertEquals(950.0, $store->fresh()->cycle_sales_usd);

        // A refund arrives as an update; a cancellation removes the order from the count.
        $this->webhook('orders/updated', ['id' => 11, 'current_total_price' => '500.00', 'currency' => 'USD', 'created_at' => now()->toIso8601String()], 'w3');
        $this->assertEquals(750.0, $store->fresh()->cycle_sales_usd);
        $this->webhook('orders/cancelled', ['id' => 12, 'current_total_price' => '250.00', 'currency' => 'USD', 'cancelled_at' => now()->toIso8601String(), 'created_at' => now()->toIso8601String()], 'w4');
        $this->assertEquals(500.0, $store->fresh()->cycle_sales_usd);
        $this->assertSame(2, StoreOrder::count());

        // Passing the limit is noticed the moment the order arrives, however early in the cycle.
        $this->webhook('orders/create', ['id' => 13, 'current_total_price' => '800.00', 'currency' => 'USD', 'created_at' => now()->toIso8601String(), 'line_items' => []], 'w5');
        $this->assertNotNull($store->fresh()->over_limit_since);
        $this->assertSame('free', $store->fresh()->over_limit_plan);
    }

    public function test_over_the_limit_means_upgrade_within_three_days_or_everything_stops(): void
    {
        $store = $this->store('free');
        $owner = $this->member($store, 'owner');
        $meter = app(SalesMeter::class);
        $manager = app(ExperienceManager::class);
        $manager->publish($manager->create($store, 'trust', 'trust-row', null), null);
        $this->travel(7)->days(); // a week into the cycle

        $this->order(1, 1300);
        $meter->refresh($store->fresh());
        $store->refresh();
        $status = $meter->status($store);
        $this->assertSame('over', $status['state']);
        $this->assertSame('starter', $status['next']['key'], 'The cheapest plan that fits is suggested.');
        $this->assertTrue($status['deadline']->isSameDay(now()->addDays(3)));
        $this->assertCount(1, app(StorefrontPublisher::class)->payload($store)['experiences'], 'Still live during the grace period.');
        $this->get('/app', $this->as($owner))->assertOk()->assertSee('Upgrade required');

        // Three days later, with no upgrade: everything stops and the app only offers Billing.
        $this->travel(3)->days();
        $this->travel(1)->hours();
        $meter->refreshIfStale($store->fresh(), 0);
        $store->refresh();
        $this->assertTrue($store->offersSuspended());
        $this->assertSame([], app(StorefrontPublisher::class)->payload($store)['experiences']);
        $this->assertSame('published', $store->experiences()->first()->status, 'Nothing is deleted or unpublished.');
        $this->get('/app', $this->as($owner))->assertRedirectContains('/app/settings/billing');
        $this->get('/app/cro', $this->as($owner))->assertRedirectContains('upgrade_required');
        $this->get('/app/settings/billing', $this->as($owner))->assertOk()->assertSee('All features are stopped')->assertSee('Upgrade plan');

        // Upgrading to a plan that fits turns everything back on straight away.
        $store->refresh()->forceFill(['plan' => 'starter'])->save();
        $meter->evaluate($store);
        $store->refresh();
        $this->assertFalse($store->offersSuspended());
        $this->assertNull($store->over_limit_since);
        $this->assertCount(1, app(StorefrontPublisher::class)->payload($store)['experiences']);
    }

    public function test_a_new_cycle_resets_the_count_but_not_a_stopped_store(): void
    {
        $meter = app(SalesMeter::class);

        // A store under its limit simply starts again.
        $fine = $this->store('starter');
        $this->travel(2)->days();
        $this->order(1, 6000);
        $meter->refresh($fine);
        $this->assertEquals(6000.0, $fine->fresh()->cycle_sales_usd);
        $firstCycle = $fine->fresh()->cycle_started_at;

        $this->travel(30)->days();
        $meter->refreshIfStale($fine->fresh(), 0);
        $fine->refresh();
        $this->assertEquals(0.0, $fine->cycle_sales_usd);
        $this->assertTrue($fine->cycle_started_at->equalTo($firstCycle->copy()->addDays(30)));
        $cycle = SalesCycle::where('store_id', $fine->id)->sole();
        $this->assertSame([6000.0, 1, 'starter', 8000.0, false], [$cycle->sales_usd, $cycle->orders_count, $cycle->plan, $cycle->sales_limit, $cycle->over_limit]);

        // A store that went over and didn't upgrade stays stopped in the next cycle…
        $this->orders = [];
        $over = $this->store('free', ['shop_domain' => 'over.myshopify.com']);
        $this->travel(20)->days();
        $this->order(2, 1500);
        $meter->refresh($over);
        $this->travel(4)->days();
        $meter->refreshIfStale($over->fresh(), 0);
        $this->assertTrue($over->fresh()->offersSuspended());

        $this->travel(10)->days(); // into the next cycle: sales are back to zero
        $meter->refreshIfStale($over->fresh(), 0);
        $over->refresh();
        $this->assertEquals(0.0, $over->cycle_sales_usd);
        $this->assertTrue($over->offersSuspended(), 'A reset alone does not turn features back on.');
        $this->assertTrue(SalesCycle::where('store_id', $over->id)->sole()->over_limit);

        // …and a downgrade or the same plan doesn't clear it; a higher plan does.
        $meter->evaluate($over);
        $this->assertTrue($over->fresh()->offersSuspended());
        $over->forceFill(['plan' => 'starter'])->save();
        $meter->evaluate($over);
        $this->assertFalse($over->fresh()->offersSuspended());
    }

    public function test_a_refund_in_the_same_cycle_clears_the_warning(): void
    {
        $store = $this->store('starter');
        $meter = app(SalesMeter::class);
        $this->travel(1)->hours();

        $this->order(1, 9000);
        $meter->refresh($store);
        $this->assertNotNull($store->fresh()->over_limit_since);

        $this->order(1, 7000);
        $meter->refresh($store->fresh());
        $this->assertNull($store->fresh()->over_limit_since);
        $this->assertSame('near', $meter->status($store->fresh())['state']);
    }

    public function test_scale_and_test_shops_are_never_limited(): void
    {
        $meter = app(SalesMeter::class);
        $scale = $this->store('scale');
        config(['shopify.test_shops' => ['dev.myshopify.com']]);
        $dev = $this->store('free', ['shop_domain' => 'dev.myshopify.com']);
        $this->travel(1)->hours();
        $this->order(1, 250000);

        $meter->refresh($scale);
        $this->assertSame('ok', $meter->status($scale->fresh())['state']);
        $this->assertNull($meter->status($scale->fresh())['limit']);

        $meter->refresh($dev);
        $this->assertNull($dev->fresh()->over_limit_since);
    }

    public function test_the_billing_page_shows_the_cycle(): void
    {
        $store = $this->store('free');
        $owner = $this->member($store, 'owner');
        SalesCycle::create(['store_id' => $store->id, 'starts_at' => now()->subDays(30), 'ends_at' => now(), 'sales_usd' => 640, 'orders_count' => 9, 'plan' => 'free', 'sales_limit' => 1000, 'over_limit' => false]);
        StoreOrder::create(['store_id' => $store->id, 'shopify_order_id' => '77', 'amount' => 1300, 'currency' => 'USD', 'amount_usd' => 1300, 'ordered_at' => now()->addMinute()]);
        $this->travel(5)->minutes();
        $store->forceFill(['sales_checked_at' => now()])->save();

        $this->get('/app/settings/billing', $this->as($owner))->assertOk()
            ->assertSee('Store sales · this cycle')->assertSee('$1,300')->assertSee('of $1,000 on your plan')
            ->assertSee('Upgrade required by')->assertSee('Past cycle')->assertSee('$640')
            ->assertSee('Up to $8,000 in monthly store sales')->assertSee('Live offers on your plan')->assertSee('Checkout, Thank You and Order Status blocks');
    }
}
