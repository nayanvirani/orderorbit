<?php

namespace Tests\Feature;

use App\Services\Experiences\ExperienceManager;
use App\Services\Experiences\PlacementDetector;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Experiences\TemplateLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class CartDrawerTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    private bool $embed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        app(TemplateLibrary::class)->sync();

        Http::fake(fn (Request $request) => match (true) {
            str_contains($request['query'] ?? '', 'themes(first: 1') => Http::response(['data' => ['themes' => ['nodes' => [['id' => 'gid://shopify/OnlineStoreTheme/1']]]]]),
            // A theme with the app embed on (or off) and no OrderOrbit blocks placed.
            str_contains($request['query'] ?? '', 'files(filenames') => Http::response(['data' => ['theme' => ['files' => ['nodes' => [[
                'filename' => 'config/settings_data.json',
                'body' => ['content' => json_encode(['current' => ['blocks' => ['1' => ['type' => 'shopify://apps/orderorbit/blocks/app-embed/abc', 'disabled' => ! $this->embed]]]])],
            ]]]]]]),
            str_contains($request['query'] ?? '', 'currentAppInstallation { id }') => Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1']]]),
            str_contains($request['query'] ?? '', 'metafieldsSet') => Http::response(['data' => ['metafieldsSet' => ['userErrors' => []]]]),
            default => Http::response(['data' => []]),
        });
    }

    public function test_cart_upsells_show_in_the_cart_drawer_through_the_app_embed(): void
    {
        $store = $this->installedStore(['plan' => 'growth']);
        $owner = $this->member($store, 'owner');
        $manager = app(ExperienceManager::class);

        $upsell = $manager->create($store, 'cart-upsells', 'grid', $owner);
        $config = $upsell->draft_config;
        $this->assertTrue($config['content']['drawer'], 'The drawer is on by default.');
        $this->assertSame(2, $config['content']['drawer_max']);
        $config['content']['products'] = [['id' => 'gid://shopify/Product/1', 'title' => 'Serum']];
        $manager->saveDraft($upsell, $config, [], $owner);
        $manager->publish($upsell->fresh(), $owner);

        // The storefront runtime reads these to add the drawer root.
        $live = collect(app(StorefrontPublisher::class)->payload($store)['experiences'])->firstWhere('type', 'cart-upsells');
        $this->assertTrue($live['content']['drawer']);
        $this->assertSame(2, $live['content']['drawer_max']);

        // With the app embed on it counts as placed, even without a block on the cart page.
        app(PlacementDetector::class)->refresh($store);
        $this->assertSame('placed', $upsell->fresh()->placement_status);

        // Embed off, or the drawer turned off: it needs the cart-page block.
        $this->embed = false;
        app(PlacementDetector::class)->refresh($store);
        $this->assertSame('not_placed', $upsell->fresh()->placement_status);

        $this->embed = true;
        $config['content']['drawer'] = false;
        $manager->saveDraft($upsell->fresh(), $config, [], $owner);
        app(PlacementDetector::class)->refresh($store);
        $this->assertSame('not_placed', $upsell->fresh()->placement_status);

        $this->get('/app/cro/experiences/'.$upsell->id.'/edit', $this->as($owner))->assertOk()
            ->assertSee('Also show in the cart drawer')->assertSee('Products shown in the drawer');
    }

    public function test_the_drawer_runtime_is_shipped(): void
    {
        $this->get('/storefront/oo-cart-upsells.js')->assertOk();
        $js = file_get_contents(base_path('extensions/orderorbit-theme/assets/oo-cart-upsells.js'));
        $this->assertStringContainsString('cart-drawer', $js);
        $this->assertStringContainsString('getSectionsToRender', $js);
        foreach (glob(base_path('extensions/orderorbit-theme/assets/*.js')) as $file) {
            $this->assertLessThan(10000, filesize($file), basename($file).' must stay under Shopify\'s 10 KB limit.');
        }
    }
}
