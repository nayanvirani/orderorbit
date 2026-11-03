<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Experiments\Experiment;
use App\Models\FeatureFlag;
use App\Services\Experiences\OfferSync;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Experiences\TemplateLibrary;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** G39: a bundle's test versions have their own prices; checkout charges the version each shopper saw. */
class AbEveryOfferTest extends TestCase
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
                str_contains($query, 'discountAutomaticAppUpdate') => Http::response(['data' => ['discountAutomaticAppUpdate' => ['automaticAppDiscount' => ['discountId' => 'gid://shopify/DiscountAutomaticNode/77'], 'userErrors' => []]]]),
                str_contains($query, 'currentAppInstallation { id }') => Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1']]]),
                str_contains($query, 'metafieldsSet') => Http::response(['data' => ['metafieldsSet' => ['userErrors' => []]]]),
                default => Http::response(['data' => []]),
            };
        });
    }

    public function test_a_bundle_version_has_its_own_price_from_editor_to_checkout(): void
    {
        $store = $this->installedStore(['plan' => 'scale']);
        $owner = $this->member($store, 'owner');
        $this->post('/app/cro/bundles', ['model' => 'qb-classic'], $this->as($owner));
        $bundle = Experience::sole();
        $this->post("/app/cro/bundles/{$bundle->id}", ['config_json' => json_encode($bundle->draft_config), 'action' => 'publish'], $this->as($owner))->assertRedirectContains('published');

        // Without the flag, bundles can't be tested yet.
        $this->post('/app/experiments', ['experience_id' => $bundle->id], $this->as($owner))->assertRedirectContains('error=');
        FeatureFlag::create(['key' => 'ab_every_offer', 'mode' => 'all', 'enabled' => true]);
        Features::forget();
        $this->post('/app/experiments', ['experience_id' => $bundle->id], $this->as($owner))->assertRedirectContains('/edit');
        $test = Experiment::sole();
        $this->page("/app/experiments/{$test->id}/edit", $owner)->assertJsonPath('props.priced', true)->assertJsonPath('props.tiers.A.1.1', '10% off');

        // Version B in the bundle editor: 20% off two items instead of 10%.
        $this->page("/app/experiments/{$test->id}/versions/B", $owner)->assertJsonPath('component', 'bundles/editor')->assertJsonPath('props.testVersion.key', 'B');
        $config = $bundle->fresh()->publishedVersion->config;
        $config['offers'][1]['discount_value'] = 20;
        $config['settings']['visibility'] = 'products'; // ignored: a version shows where the bundle shows
        $this->post("/app/experiments/{$test->id}/versions/B", ['config' => json_encode($config)], $this->as($owner))->assertRedirectContains("/app/experiments/{$test->id}/edit");
        $b = $test->variants()->where('key', 'B')->sole();
        $this->assertSame(['price'], $b->changes);
        $this->assertSame('all', $b->config['settings']['visibility']);
        $this->assertEquals(20, $b->config['offers'][1]['discount_value']);
        $this->assertEquals(10, $bundle->fresh()->publishedVersion->config['offers'][1]['discount_value'], 'The live bundle is untouched.');

        // Launch: checkout gets version B's prices for lines tagged B; the storefront knows B's offer.
        $this->post("/app/experiments/{$test->id}", ['name' => 'Bundle 10% vs 20%', 'primary_metric' => 'conversion_rate', 'min_days' => 7, 'min_visitors' => 1000, 'min_conversions' => 100,
            'variants' => ['A' => ['allocation' => 50], 'B' => ['allocation' => 50]], 'action' => 'launch'], $this->as($owner))->assertRedirectContains('launched');
        $this->assertSame('running', $test->fresh()->status);
        $offers = OfferSync::versionOffers($bundle->fresh(), $store->fresh());
        $this->assertSame(['bq', 'B', 20.0], [$offers[0]['k'], $offers[0]['v'], (float) $offers[0]['o'][1]['v']]);
        $shown = collect(app(StorefrontPublisher::class)->payload($store->fresh())['experiences'])->firstWhere('id', $bundle->handle)['x']['variants'];
        $this->assertSame(['A', 'B'], array_column($shown, 'key'));
        $this->assertSame('B', $shown[1]['pv']);
        $this->assertEquals(20, $shown[1]['content']['offers'][1]['discount_value']);
        $this->assertArrayNotHasKey('pv', $shown[0], 'Version A lines carry no version.');

        // Apply B: it becomes the live bundle.
        $this->post("/app/experiments/{$test->id}/apply", ['variant' => 'B'], $this->as($owner))->assertRedirect();
        $this->assertEquals(20, $bundle->fresh()->publishedVersion->config['offers'][1]['discount_value']);
        $this->assertSame('completed', $test->fresh()->status);
        $this->assertSame([], OfferSync::versionOffers($bundle->fresh(), $store->fresh()), 'No version prices once the test ends.');
    }
}
