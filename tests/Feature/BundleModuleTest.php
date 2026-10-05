<?php

namespace Tests\Feature;

use App\Experiences\BundleSchema;
use App\Models\Experience;
use App\Services\Experiences\TemplateLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class BundleModuleTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        app(TemplateLibrary::class)->sync();
        Http::fake(function (Request $request) {
            $query = $request['query'] ?? '';

            return match (true) {
                str_contains($query, 'discountAutomaticAppCreate') => Http::response(['data' => ['discountAutomaticAppCreate' => ['automaticAppDiscount' => ['discountId' => 'gid://shopify/DiscountAutomaticNode/77'], 'userErrors' => []]]]),
                str_contains($query, 'discountAutomaticDelete') => Http::response(['data' => ['discountAutomaticDelete' => ['userErrors' => []]]]),
                str_contains($query, 'currentAppInstallation { id }') => Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1']]]),
                str_contains($query, 'metafieldsSet') => Http::response(['data' => ['metafieldsSet' => ['userErrors' => []]]]),
                default => Http::response(['data' => []]),
            };
        });
    }

    public function test_every_model_has_valid_defaults_except_product_picks(): void
    {
        foreach (array_keys(BundleSchema::models()) as $model) {
            [$config, $errors] = BundleSchema::normalize(BundleSchema::defaults($model));
            $this->assertSame(BundleSchema::model($model)['type'], $config['bundle_type'], $model);
            foreach (array_keys($errors) as $key) {
                $this->assertMatchesRegularExpression('/(products|product|gifts\.\d+|mix\.pool)$/', $key, "{$model}: unexpected error {$key}");
            }
        }
    }

    public function test_type_model_and_editor_screens(): void
    {
        $store = $this->installedStore();
        $owner = $this->member($store, 'owner');

        $this->page('/app/cro/bundles', $owner)->assertOk()->assertJsonPath('component', 'bundles/index')->assertJsonPath('props.bundles', []);
        $labels = array_column($this->page('/app/cro/bundles/new', $owner)->assertOk()->json('props.types'), 'label');
        $this->assertContains('Quantity breaks', $labels);
        $this->assertContains('Bundle builder & mix and match', $labels);
        $this->assertContains('Fixed bundle + gifts', $labels);
        $names = array_column($this->page('/app/cro/bundles/new/quantity-breaks', $owner)->assertOk()->json('props.models'), 'name');
        $this->assertContains('Classic quantity breaks', $names);
        $this->assertContains('1 bought = 1 free', $names);
        $this->get('/app/cro/bundles/new/nope', $this->as($owner))->assertNotFound();

        $this->post('/app/cro/bundles', ['model' => 'qb-inversion', 'preset' => 'blue'], $this->as($owner))->assertRedirectContains('/app/cro/bundles/');
        $bundle = Experience::where('type', 'bundles')->firstOrFail();
        $this->assertSame('Quantity inversion offer', $bundle->name);
        $this->assertSame('#2448ff', $bundle->draft_config['design']['accent']);
        $this->assertSame('20% Additional discount', $bundle->draft_config['offers'][0]['label']);

        $this->page("/app/cro/bundles/{$bundle->id}", $owner)->assertOk()->assertJsonPath('component', 'bundles/editor')->assertJsonPath('props.config.bundle_type', 'quantity-breaks');
        $this->get("/app/cro/experiences/{$bundle->id}/edit", $this->as($owner))->assertRedirectContains("/app/cro/bundles/{$bundle->id}");
        $this->get('/app/cro/experiences/new?type=bundles', $this->as($owner))->assertRedirectContains('/app/cro/bundles/new');
        $this->page('/app/cro/bundles', $owner)->assertOk()->assertJsonPath('props.bundles.0.name', 'Quantity inversion offer')->assertSee('All products');
    }

    public function test_save_validate_publish_and_toggle(): void
    {
        $store = $this->installedStore();
        $owner = $this->member($store, 'owner');
        $this->post('/app/cro/bundles', ['model' => 'qb-classic'], $this->as($owner));
        $bundle = Experience::firstOrFail();

        // Invalid: shown on the chosen products, but none chosen. The draft still saves.
        $config = $bundle->draft_config;
        $config['settings']['visibility'] = 'products';
        $config['offers'][2]['discount_value'] = 25;
        $this->post("/app/cro/bundles/{$bundle->id}", ['config_json' => json_encode($config), 'name' => 'Serum tiers', 'action' => 'publish'], $this->as($owner))
            ->assertStatus(422)->assertSee('Fix the highlighted settings before publishing.');
        $this->assertSame('draft', $bundle->fresh()->status);
        $this->assertEquals(25, $bundle->fresh()->draft_config['offers'][2]['discount_value']);
        $this->assertSame('Serum tiers', $bundle->fresh()->name);

        // Valid: publishes with the tier discounts.
        $config['settings']['visibility'] = 'all';
        $this->post("/app/cro/bundles/{$bundle->id}", ['config_json' => json_encode($config), 'action' => 'publish'], $this->as($owner))
            ->assertRedirectContains('notice=published');
        $this->assertSame('published', $bundle->fresh()->status);
        $this->assertSame('gid://shopify/DiscountAutomaticNode/77', $bundle->fresh()->shopify_discount_id);
        Http::assertSent(function (Request $r) {
            if (! str_contains($r['query'] ?? '', 'discountAutomaticAppCreate')) {
                return false;
            }
            $offer = json_decode(json_decode($r->body(), true)['variables']['discount']['metafields'][0]['value'], true)['offers'][0];

            return $offer['k'] === 'bq' && $offer['o'][1] === ['q' => 2, 't' => 'percentage', 'v' => 10] && $offer['o'][2]['v'] === 25;
        });

        // The list switch pauses it and removes the discount.
        $this->post("/app/cro/bundles/{$bundle->id}/toggle", [], $this->as($owner))->assertRedirectContains('notice=paused');
        $this->assertSame('paused', $bundle->fresh()->status);
        $this->assertNull($bundle->fresh()->shopify_discount_id);
    }

    public function test_storefront_payload(): void
    {
        $config = BundleSchema::defaults('qb-classic');
        $config['settings']['excluded'] = [['id' => 'gid://shopify/Product/5', 'title' => 'Gift card']];
        $config['settings']['visibility'] = 'products';
        $config['settings']['products'] = [['id' => 'gid://shopify/Product/1', 'title' => 'Serum']];
        $config['offers'][2]['visible'] = false;

        $payload = BundleSchema::payload(BundleSchema::normalize($config)[0]);

        $this->assertCount(2, $payload['content']['offers'], 'Hidden offers never reach the storefront.');
        $this->assertSame([0, 1], array_column($payload['content']['offers'], 'index'), 'Offers keep their index for pricing.');
        $this->assertSame([['id' => 'gid://shopify/Product/5']], $payload['targeting']['exclude']);
        $this->assertSame('gid://shopify/Product/1', $payload['targeting']['products'][0]['id']);
        $this->assertSame(['product'], $payload['targeting']['page_types']);
        $this->assertSame('above_atc', $payload['behavior']['position']);
        $this->assertArrayNotHasKey('excluded', $payload['content']['settings']);
    }

    public function test_legacy_bundles_are_migrated(): void
    {
        [$config] = BundleSchema::normalize(['content' => [
            'bundle_mode' => 'fixed', 'headline' => 'Get the set', 'discount_type' => 'percentage', 'discount_value' => 12,
            'products' => [['id' => 'gid://shopify/Product/1', 'title' => 'A'], ['id' => 'gid://shopify/Product/2', 'title' => 'B']],
        ]]);

        $this->assertSame('fixed', $config['bundle_type']);
        $this->assertSame('Get the set', $config['settings']['title']);
        $this->assertSame('multi', $config['offers'][1]['kind']);
        $this->assertCount(2, $config['offers'][1]['products']);
        $this->assertSame(12.0, $config['offers'][1]['discount_value']);

        [$mix] = BundleSchema::normalize(['content' => ['bundle_mode' => 'mix', 'min_items' => 2, 'max_items' => 3, 'discount_value' => 15,
            'products' => [['id' => 'gid://shopify/Product/1', 'title' => 'A'], ['id' => 'gid://shopify/Product/2', 'title' => 'B']]]]);
        $this->assertSame('mix-match', $mix['bundle_type']);
        $this->assertSame([['count' => 2, 'discount' => 15.0]], $mix['mix']['tiers']);
    }
}
