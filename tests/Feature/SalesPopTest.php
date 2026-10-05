<?php

namespace Tests\Feature;

use App\Models\RecentPurchase;
use App\Services\Experiences\ExperienceManager;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Experiences\TemplateLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class SalesPopTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        app(TemplateLibrary::class)->sync();
    }

    private function fakeShopify(): void
    {
        Http::fake(fn (Request $request) => match (true) {
            str_contains($request['query'] ?? '', 'currentAppInstallation { id }') => Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1']]]),
            str_contains($request['query'] ?? '', 'metafieldsSet') => Http::response(['data' => ['metafieldsSet' => ['userErrors' => []]]]),
            str_contains($request['query'] ?? '', 'themes(first: 1') => Http::response(['data' => ['themes' => ['nodes' => []]]]),
            str_contains($request['query'] ?? '', 'nodes(ids: $ids)') => Http::response(['data' => ['nodes' => [
                ['id' => 'gid://shopify/Product/21', 'title' => 'Trail Tee', 'handle' => 'trail-tee', 'featuredMedia' => ['preview' => ['image' => ['url' => 'https://cdn.shopify.com/tee.jpg']]]],
            ]]]),
            str_contains($request['query'] ?? '', 'orders(first: 50') => Http::response(['data' => ['orders' => ['nodes' => [
                ['id' => 'gid://shopify/Order/902', 'createdAt' => now()->subHours(2)->toIso8601String(), 'lineItems' => ['nodes' => [
                    ['product' => ['id' => 'gid://shopify/Product/31', 'title' => 'Night Cream', 'handle' => 'night-cream', 'featuredMedia' => null]],
                    ['product' => null],
                ]]],
                ['id' => 'gid://shopify/Order/901', 'createdAt' => now()->subDays(3)->toIso8601String(), 'lineItems' => ['nodes' => [
                    ['product' => ['id' => 'gid://shopify/Product/32', 'title' => 'Lip Balm', 'handle' => 'lip-balm', 'featuredMedia' => ['preview' => ['image' => ['url' => 'https://cdn.shopify.com/balm.jpg']]]]],
                ]]],
            ]]]]),
            default => Http::response(['data' => []]),
        });
    }

    private function order(array $auth, string $id, array $lines, ?string $country = 'CA')
    {
        return $this->call('POST', '/api/pixel', [], [], [], ['CONTENT_TYPE' => 'text/plain'],
            json_encode($auth + ['k' => 'o', 'id' => $id, 'v' => 50, 'c' => 'USD', 'cc' => $country, 'l' => $lines]));
    }

    public function test_real_orders_feed_the_sales_pop(): void
    {
        $this->fakeShopify();
        $store = $this->installedStore(['pixel_token' => str_repeat('a', 40)]);
        $auth = ['t' => str_repeat('a', 40), 's' => $store->shop_domain];

        $this->order($auth, 'gid://shopify/Order/1', [
            ['q' => 1, 'v' => 29, 'p' => 'gid://shopify/Product/11', 't' => 'Glow <b>Serum</b>', 'u' => 'https://demo.myshopify.com/products/glow-serum?x=1', 'i' => 'https://cdn.shopify.com/s/files/serum.jpg'],
            ['q' => 2, 'v' => 10, 'p' => 'gid://shopify/Product/11', 't' => 'Glow Serum'], // same product twice: one pop
            ['q' => 1, 'v' => 5, 'p' => 'gid://shopify/Product/12', 't' => 'Lip Balm', 'u' => 'javascript:alert(1)', 'i' => 'javascript:alert(1)'],
            ['q' => 1, 'v' => 5, 't' => 'No product id'],
        ])->assertNoContent();
        $this->order($auth, 'gid://shopify/Order/1', [['p' => '11', 't' => 'Glow Serum']]); // duplicate order event

        $this->assertSame(2, RecentPurchase::count());
        $serum = RecentPurchase::where('product_id', '11')->first();
        $this->assertSame('Glow Serum', $serum->title, 'Markup is stripped.');
        $this->assertSame('/products/glow-serum', $serum->url, 'Only the path is kept.');
        $this->assertSame('CA', $serum->country);
        $balm = RecentPurchase::where('product_id', '12')->first();
        $this->assertNull($balm->image);

        // No published Sales pop: the feed is empty.
        $this->getJson('/api/sales-pop?shop='.$store->shop_domain)->assertOk()->assertExactJson(['items' => []])
            ->assertHeader('Access-Control-Allow-Origin', '*');

        $owner = $this->member($store, 'owner');
        $manager = app(ExperienceManager::class);
        $pop = $manager->create($store, 'sales-pop', 'rounded-pill', $owner);
        $manager->publish($pop, $owner);
        cache()->flush();

        $items = $this->getJson('/api/sales-pop?shop='.$store->shop_domain.'&days=7')->assertOk()->json('items');
        $this->assertCount(2, $items);
        $this->assertSame(['t', 'u', 'i', 'c', 'at'], array_keys(collect($items)->firstWhere('t', 'Glow Serum')));
        $this->assertSame([], $this->getJson('/api/sales-pop?shop=not a shop')->json('items'));

        // Older than the window: hidden.
        RecentPurchase::query()->update(['purchased_at' => now()->subDays(10)]);
        cache()->flush();
        $this->assertSame([], $this->getJson('/api/sales-pop?shop='.$store->shop_domain.'&days=7')->json('items'));

        // The storefront payload points the runtime at the feed with the merchant's window.
        $live = collect(app(StorefrontPublisher::class)->payload($store)['experiences'])->firstWhere('type', 'sales-pop');
        $this->assertSame('pill', $live['style']);
        $this->assertStringContainsString('/api/sales-pop?shop='.$store->shop_domain.'&days=7', $live['content']['feed']);
        $this->assertSame('bottom-left', $live['content']['position_desktop']);
    }

    public function test_only_the_newest_purchases_are_kept(): void
    {
        $this->fakeShopify();
        $store = $this->installedStore(['pixel_token' => str_repeat('b', 40)]);
        $auth = ['t' => str_repeat('b', 40), 's' => $store->shop_domain];

        for ($i = 1; $i <= RecentPurchase::KEEP + 3; $i++) {
            $this->order($auth, "gid://shopify/Order/{$i}", [['p' => (string) $i, 't' => "Product {$i}"]]);
        }

        $this->assertSame(RecentPurchase::KEEP, RecentPurchase::count());
        $this->assertFalse(RecentPurchase::where('product_id', '1')->exists());
    }

    public function test_builder_explains_the_app_embed(): void
    {
        $this->fakeShopify();
        $store = $this->installedStore(['plan' => 'growth']);
        $owner = $this->member($store, 'owner');

        $this->get('/app/cro/features/sales-pop', $this->as($owner))->assertOk()->assertSee('Turn on app embed')->assertSee('activateAppId', false);
        $this->get('/app/cro/experiences/new?type=sales-pop', $this->as($owner))->assertOk()->assertSee('Rounded pill')->assertSee('Dark toast');
        $this->post('/app/cro/experiences', ['type' => 'sales-pop', 'template' => 'slim-bar'], $this->as($owner))->assertRedirectContains('/edit');
        $edit = $this->get('/app/cro/experiences/1/edit', $this->as($owner))->assertOk();
        $edit->assertSee('Position on mobile')->assertSee('Close automatically after (seconds)')->assertSee('No theme block needed');
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

    public function test_orders_from_shopify_feed_the_sales_pop(): void
    {
        $this->fakeShopify();
        config(['shopify.scopes' => 'read_products,write_discounts,read_orders']);
        $store = $this->installedStore(['scopes' => 'read_products,write_discounts,read_orders', 'pixel_token' => str_repeat('c', 40)]);
        $owner = $this->member($store, 'owner');
        $manager = app(ExperienceManager::class);

        // Publishing a Sales pop imports recent orders when there are none yet.
        $pop = $manager->create($store, 'sales-pop', 'classic-card', $owner);
        $manager->publish($pop, $owner);
        $this->assertSame(['Lip Balm', 'Night Cream'], RecentPurchase::orderBy('purchased_at')->pluck('title')->all());
        $this->assertSame('/products/night-cream', RecentPurchase::where('product_id', '31')->value('url'));
        $this->assertTrue(RecentPurchase::where('product_id', '32')->first()->purchased_at->lt(now()->subDays(2)), 'The real order time is kept.');

        // A new order arrives by webhook; the product link and image come from the Admin API.
        $this->webhook('orders/create', ['id' => 950, 'created_at' => now()->toIso8601String(), 'line_items' => [
            ['product_id' => 21, 'title' => 'Trail Tee - M'], ['product_id' => null, 'title' => 'Custom item'],
        ], 'shipping_address' => ['country_code' => 'gb', 'name' => 'Jane Doe', 'address1' => '1 High St']], 'wh-order-1')->assertNoContent();
        $tee = RecentPurchase::where('product_id', '21')->first();
        $this->assertSame(['Trail Tee', '/products/trail-tee', 'https://cdn.shopify.com/tee.jpg', 'GB'], [$tee->title, $tee->url, $tee->image, $tee->country]);
        $this->assertSame('950', $tee->order_ref);

        // The pixel's report of the same order fills the country on rows that lack it, without duplicates.
        $this->call('POST', '/api/pixel', [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode([
            't' => str_repeat('c', 40), 's' => $store->shop_domain, 'k' => 'o', 'id' => 'gid://shopify/Order/902', 'v' => 34, 'c' => 'USD', 'cc' => 'CA',
            'l' => [['p' => 'gid://shopify/Product/31', 't' => 'Night Cream']],
        ]));
        $this->assertSame(1, RecentPurchase::where('product_id', '31')->count());
        $this->assertSame('CA', RecentPurchase::where('product_id', '31')->value('country'));

        // The experience page shows what's ready and offers an import.
        $this->page('/app/cro/experiences/'.$pop->id, $owner)->assertOk()->assertJsonPath('props.salesPop.recent', 3)->assertJsonPath('props.salesPop.canRead', true);
        $this->post('/app/cro/experiences/'.$pop->id.'/import-orders', [], $this->as($owner))->assertRedirectContains('orders_imported');
    }

    public function test_the_app_explains_when_shopify_blocks_order_access(): void
    {
        Http::fake(fn (Request $request) => match (true) {
            str_contains($request['query'] ?? '', 'orders(first: 50') => Http::response(['errors' => [['message' => 'This app is not approved to access the Order object. See https://shopify.dev/docs/apps/launch/protected-customer-data for more details.', 'extensions' => ['code' => 'ACCESS_DENIED']]]]),
            str_contains($request['query'] ?? '', 'currentAppInstallation { id }') => Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1']]]),
            str_contains($request['query'] ?? '', 'metafieldsSet') => Http::response(['data' => ['metafieldsSet' => ['userErrors' => []]]]),
            default => Http::response(['data' => []]),
        });
        config(['shopify.scopes' => 'read_products,write_discounts,read_orders']);
        $store = $this->installedStore(['scopes' => 'read_products,write_discounts,read_orders']);
        $owner = $this->member($store, 'owner');
        $manager = app(ExperienceManager::class);
        $pop = $manager->create($store, 'sales-pop', 'classic-card', $owner);
        $manager->publish($pop, $owner); // publishing still works

        $this->post('/app/cro/experiences/'.$pop->id.'/import-orders', [], $this->as($owner))->assertRedirectContains('orders_blocked');
        $this->page('/app/cro/experiences/'.$pop->id, $owner)->assertOk()->assertJsonPath('props.salesPop.blocked', true);
    }
}
