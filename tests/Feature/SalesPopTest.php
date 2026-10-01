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
}
