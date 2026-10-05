<?php

namespace Tests\Feature;

use App\Models\CroTemplate;
use App\Models\Experience;
use App\Services\Experiences\ExperienceManager;
use App\Services\Experiences\PublishException;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Experiences\TemplateLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class ExperienceEngineTest extends TestCase
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
        Http::fake(function (Request $request) {
            $query = $request['query'] ?? '';

            return match (true) {
                str_contains($query, 'productCreate') => Http::response(['data' => ['productCreate' => ['product' => ['id' => 'gid://shopify/Product/500', 'variants' => ['nodes' => [['id' => 'gid://shopify/ProductVariant/501']]]], 'userErrors' => []]]]),
                str_contains($query, 'productVariantsBulkUpdate') => Http::response(['data' => ['productVariantsBulkUpdate' => ['userErrors' => []]]]),
                str_contains($query, 'productUpdate') => Http::response(['data' => ['productUpdate' => ['product' => ['id' => 'gid://shopify/Product/500'], 'userErrors' => []]]]),
                str_contains($query, 'cartTransformCreate') => Http::response(['data' => ['cartTransformCreate' => ['cartTransform' => ['id' => 'gid://shopify/CartTransform/7'], 'userErrors' => []]]]),
                str_contains($query, 'discountAutomaticAppCreate') => Http::response(['data' => ['discountAutomaticAppCreate' => ['automaticAppDiscount' => ['discountId' => 'gid://shopify/DiscountAutomaticNode/77'], 'userErrors' => []]]]),
                str_contains($query, 'discountAutomaticDelete') => Http::response(['data' => ['discountAutomaticDelete' => ['userErrors' => []]]]),
                str_contains($query, 'currentAppInstallation { id }') => Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1']]]),
                str_contains($query, 'metafieldsSet') => Http::response(['data' => ['metafieldsSet' => ['userErrors' => []]]]),
                str_contains($query, 'themes(first: 1') => Http::response(['data' => ['themes' => ['nodes' => []]]]),
                default => Http::response(['data' => []]),
            };
        });
    }

    public function test_template_library_is_versioned_and_idempotent(): void
    {
        $count = CroTemplate::count();
        $this->assertGreaterThanOrEqual(40, $count);

        $stats = app(TemplateLibrary::class)->sync();
        $this->assertSame(['created' => 0, 'versioned' => 0], $stats);
        $this->assertSame($count, CroTemplate::count());
    }

    public function test_publish_snapshots_a_version_and_writes_the_storefront_metafield(): void
    {
        $this->fakeShopify();
        $store = $this->installedStore(['plan' => 'starter']);
        $owner = $this->member($store, 'owner');
        $manager = app(ExperienceManager::class);

        $experience = $manager->create($store, 'shipping-bar', 'progress', $owner);
        $version = $manager->publish($experience, $owner, 'First go');

        $this->assertSame(1, $version->version);
        $this->assertSame('published', $experience->fresh()->status);
        Http::assertSent(function (Request $request) {
            if (! str_contains($request['query'] ?? '', 'metafieldsSet')) {
                return false;
            }
            $field = json_decode($request->body(), true)['variables']['metafields'][0];
            $payload = json_decode($field['value'], true);

            return $field['namespace'] === 'orderorbit' && $field['key'] === 'experiences'
                && $payload['experiences'][0]['type'] === 'shipping-bar' && $payload['experiences'][0]['style'] === 'banner';
        });

        // Editing the draft doesn't change what's live until the next publish.
        $config = $experience->draft_config;
        $config['content']['progress_message'] = 'New copy {remaining}';
        $manager->saveDraft($experience, $config, [], $owner);
        $live = app(StorefrontPublisher::class)->payload($store)['experiences'][0];
        $this->assertNotSame('New copy {remaining}', $live['content']['progress_message']);

        $this->assertSame(2, $manager->publish($experience->fresh(), $owner)->version);
        $this->assertSame('New copy {remaining}', app(StorefrontPublisher::class)->payload($store)['experiences'][0]['content']['progress_message']);
    }

    public function test_paused_and_archived_experiences_leave_the_storefront(): void
    {
        $this->fakeShopify();
        $store = $this->installedStore(['plan' => 'growth']);
        $manager = app(ExperienceManager::class);
        $experience = $manager->create($store, 'trust', 'trust-row', null);
        $manager->publish($experience, null);

        $manager->pause($experience->fresh());
        $this->assertSame([], app(StorefrontPublisher::class)->payload($store)['experiences']);

        $manager->resume($experience->fresh());
        $this->assertCount(1, app(StorefrontPublisher::class)->payload($store)['experiences']);

        $manager->archive($experience->fresh());
        $this->assertSame([], app(StorefrontPublisher::class)->payload($store)['experiences']);
        $this->assertSame('paused', tap($experience->fresh(), fn ($e) => $manager->unarchive($e))->fresh()->status);
    }

    public function test_plan_features_and_limits_and_downgrades(): void
    {
        $this->fakeShopify();
        $store = $this->installedStore(['plan' => 'free']);
        $manager = app(ExperienceManager::class);
        $ready = function (string $type, string $template) use ($manager, $store) {
            $experience = $manager->create($store, $type, $template, null);
            $config = $experience->draft_config;
            $config['content']['products'] = [['id' => 'gid://shopify/Product/1', 'title' => 'Serum']];
            if ($type === 'preorder') {
                $config['content']['ship_date'] = now()->addDays(30)->toIso8601String();
            }
            $manager->saveDraft($experience, $config, [], null);

            return $experience->fresh();
        };
        $refused = function (Experience $experience, string $message) use ($manager) {
            try {
                $manager->publish($experience, null);
                $this->fail($message);
            } catch (PublishException $e) {
                $this->assertSame(['plan', $message], [$e->reason, $e->getMessage()]);
            }
        };

        // Free (trust pinned to 1 for this store): one live of each kind; cart upsells aren't included.
        $store->forceFill(['entitlements' => ['limits' => ['trust' => 1]]])->save();
        $manager->publish($manager->create($store, 'trust', 'trust-row', null), null);
        $refused($manager->create($store, 'trust', 'review-card', null), 'The Free plan includes 1 live trust block. Pause or archive one that\'s live, or upgrade for more.');
        $refused($ready('cart-upsells', 'grid'), 'Cart upsells & cross-sell isn\'t included in your plan. Upgrade to publish it.');

        // Starter: more of each, plus cart upsells.
        $store->forceFill(['plan' => 'starter', 'entitlements' => null])->save();
        $manager->publish($store->experiences()->where('type', 'cart-upsells')->first()->fresh(), null);
        $manager->publish($ready('cart-upsells', 'carousel'), null);
        $manager->publish($ready('preorder', 'classic-card'), null);
        $manager->publish($manager->create($store, 'sales-pop', 'classic-card', null), null);
        $this->travel(1)->minutes();
        $manager->publish($store->experiences()->where('type', 'trust')->where('status', 'draft')->first()->fresh(), null);
        $this->assertSame(6, app(\App\Services\Usage::class)->current($store->fresh(), 'active_experiences'));
        // The total cap counts every live offer.
        $store->forceFill(['entitlements' => ['limits' => ['active_experiences' => 6]]])->save();
        $refused($manager->create($store->fresh(), 'trust', 'rating-strip', null), 'The Starter plan includes 6 live widgets in total. Pause or archive one that\'s live, or upgrade for more.');
        $store->forceFill(['entitlements' => null])->save();

        // Back to Free: cart upsells and the older trust block are paused (marked, not deleted);
        // one of each kind Free includes stays live.
        $store->forceFill(['plan' => 'free', 'entitlements' => ['limits' => ['trust' => 1]]])->save();
        $this->assertSame(3, app(\App\Services\Billing\PlanLimits::class)->apply($store->fresh()));
        $status = fn (string $type) => $store->experiences()->where('type', $type)->orderBy('id')->pluck('status')->all();
        $this->assertSame(['paused', 'paused'], $status('cart-upsells'));
        $this->assertSame(['published'], $status('sales-pop'));
        $this->assertSame(['published'], $status('preorder'));
        $this->assertSame(['paused', 'published', 'draft'], $status('trust'));
        $this->assertSame(3, $store->experiences()->where('paused_by_plan', true)->count());
        $this->assertSame(['preorder', 'sales-pop', 'trust'], collect(app(StorefrontPublisher::class)->payload($store->fresh())['experiences'])->pluck('type')->sort()->values()->all());
        $this->assertSame(0, app(\App\Services\Billing\PlanLimits::class)->apply($store->fresh()), 'Already within the plan.');

        // Upgrading again brings back what the plan change paused.
        $store->forceFill(['plan' => 'starter', 'entitlements' => null])->save();
        $this->assertSame(3, app(\App\Services\Billing\PlanLimits::class)->apply($store->fresh()));
        $this->assertSame(['published', 'published'], $status('cart-upsells'));
        $this->assertSame(['published', 'published', 'draft'], $status('trust'));
        $this->assertSame(0, $store->experiences()->where('paused_by_plan', true)->count());
    }

    public function test_free_runs_one_bundle_gift_campaign_shipping_bar_and_widget_together(): void
    {
        $this->fakeShopify();
        $store = $this->installedStore(['plan' => 'free']);
        $manager = app(ExperienceManager::class);
        $manager->publish($manager->create($store, 'trust', 'trust-row', null), null);
        $manager->publish($manager->create($store, 'shipping-bar', 'minimal', null), null);
        foreach ([['bundles', 'fx-classic'], ['progressive-gifts', array_key_first(\App\Experiences\Registry::templates('progressive-gifts'))]] as [$type, $template]) {
            $experience = $manager->create($store, $type, $template, null);
            $experience->forceFill(['status' => 'published', 'published_at' => now(), 'published_version_id' => $experience->versions()->create(['version' => 1, 'config' => $experience->draft_config, 'template_key' => $template])->id])->save();
        }
        $usage = app(\App\Services\Usage::class);
        $this->assertSame([4, 1, 1, 1, 1], array_map(fn ($m) => $usage->current($store->fresh(), $m), ['active_experiences', 'bundles', 'free_gifts', 'shipping_bars', 'trust']));
        $this->assertSame(0, app(\App\Services\Billing\PlanLimits::class)->apply($store->fresh()), 'All within Free.');

        // A second shipping bar is over Free's one.
        try {
            $manager->publish($manager->create($store, 'shipping-bar', 'progress', null), null);
            $this->fail('Free includes one shipping bar.');
        } catch (PublishException $e) {
            $this->assertStringContainsString('includes 1 live shipping bar', $e->getMessage());
        }
    }

    public function test_bundles_publish_through_the_cart_transform(): void
    {
        $this->fakeShopify();
        $store = $this->installedStore(['plan' => 'growth']);
        $manager = app(ExperienceManager::class);
        $experience = $manager->create($store, 'bundles', 'fx-classic', null, 'Skincare bundle');
        $config = $experience->draft_config;
        $config['offers'][1]['products'] = [
            ['id' => 'gid://shopify/Product/11', 'title' => 'Serum', 'handle' => 'serum', 'price' => 20, 'variant_id' => 'gid://shopify/ProductVariant/111'],
            ['id' => 'gid://shopify/Product/12', 'title' => 'Cream', 'handle' => 'cream', 'price' => 30, 'variant_id' => 'gid://shopify/ProductVariant/121'],
        ];
        $experience->update(['draft_config' => $config]);

        $manager->publish($experience->fresh(), null);

        $fresh = $experience->fresh();
        $this->assertNull($fresh->bundle_product_id);
        $this->assertNull($fresh->shopify_discount_id, 'The pack is priced by the cart transform; the single-product offer has no saving.');
        $this->assertSame('gid://shopify/CartTransform/7', $store->fresh()->cart_transform_id);

        // No extra product: the bundle line uses the main product's own variant.
        Http::assertNotSent(fn (Request $r) => str_contains($r['query'] ?? '', 'productCreate'));
        Http::assertSent(function (Request $r) use ($experience) {
            if (! str_contains($r['query'] ?? '', 'cartTransformCreate')) {
                return false;
            }
            $bundle = json_decode(json_decode($r->body(), true)['variables']['metafields'][0]['value'], true)['bundles'][0];

            return json_decode($r->body(), true)['variables']['handle'] === 'orderorbit-bundles'
                && $bundle['id'] === $experience->handle && ! isset($bundle['parent'])
                && $bundle['o'][0] === null && $bundle['o'][1]['p'] === ['11', '12'] && $bundle['o'][1]['t'] === 'percentage' && $bundle['o'][1]['v'] == 15;
        });

        // Pausing takes the bundle out of the transform.
        $manager->pause($experience->fresh());
        Http::assertSent(fn (Request $r) => str_contains($r['query'] ?? '', 'metafieldsSet') && (json_decode($r->body(), true)['variables']['metafields'][0]['ownerId'] ?? null) === 'gid://shopify/CartTransform/7'
            && json_decode(json_decode($r->body(), true)['variables']['metafields'][0]['value'], true) === ['bundles' => []]);
    }

    public function test_experiences_without_a_saving_create_no_discount(): void
    {
        $this->fakeShopify();
        $store = $this->installedStore(['plan' => 'growth']);
        $manager = app(ExperienceManager::class);
        $manager->publish($manager->create($store, 'shipping-bar', 'minimal', null), null);
        $manager->publish($manager->create($store, 'trust', 'trust-row', null), null);

        Http::assertNotSent(fn (Request $request) => str_contains($request['query'] ?? '', 'discountAutomatic'));
    }

    public function test_no_plan_means_no_publishing(): void
    {
        $store = $this->installedStore(['plan' => null]);
        $manager = app(ExperienceManager::class);

        $this->expectExceptionMessage('Choose a plan');
        $manager->publish($manager->create($store, 'countdown', 'banner', null), null);
    }

    public function test_restore_version_and_discard_changes(): void
    {
        $this->fakeShopify();
        $store = $this->installedStore(['plan' => 'growth']);
        $manager = app(ExperienceManager::class);
        $experience = $manager->create($store, 'trust', 'review-card', null);
        $v1 = $manager->publish($experience, null);

        $config = $experience->fresh()->draft_config;
        $config['content']['headline'] = 'Changed';
        $manager->saveDraft($experience->fresh(), $config, [], null);
        $manager->publish($experience->fresh(), null);

        $manager->restoreVersion($experience->fresh(), $v1, null);
        $this->assertSame('Loved by our customers', $experience->fresh()->draft_config['content']['headline']);
        $this->assertTrue($experience->fresh()->has_unpublished_changes);

        $manager->discardChanges($experience->fresh());
        $this->assertSame('Changed', $experience->fresh()->draft_config['content']['headline']);
    }

    public function test_feature_navigation_and_pages(): void
    {
        $this->fakeShopify();
        $store = $this->installedStore(['plan' => 'growth']);
        $owner = $this->member($store, 'owner');
        $manager = app(ExperienceManager::class);
        $bundle = $manager->create($store, 'bundles', 'fx-fbt', null, 'Skincare routine');
        $this->assertSame('fbt', $bundle->draft_config['settings']['style'], 'Models preset their layout.');

        // Every type merchants can create belongs to exactly one feature.
        $grouped = collect(\App\Experiences\Registry::features())->flatMap(fn ($f) => $f['types'])->sort()->values()->all();
        $this->assertSame(collect(array_keys(\App\Experiences\Registry::creatable()))->sort()->values()->all(), $grouped);

        $this->get('/app', $this->as($owner))->assertOk()
            ->assertSee('/app/cro?', false)->assertSee('/app/analytics', false)->assertDontSee('Volume discounts');
        // The CRO sub-menu lists every conversion feature.
        $nav = $this->page('/app/cro', $owner)->assertOk()->assertJsonPath('shared.nav.section', 'cro')->json('shared.nav.groups');
        $items = collect($nav)->flatMap(fn ($g) => $g['items']);
        $this->assertContains('/app/cro/progressive-gifts', $items->pluck('href')->all());
        $this->assertContains('All widgets', $items->pluck('label')->all());
        $this->assertContains('Templates', $items->pluck('label')->all());
        foreach (['cart-upsells', 'countdown', 'sticky-atc', 'trust'] as $feature) {
            $this->page("/app/cro/features/{$feature}", $owner)->assertOk()->assertJsonPath('component', 'cro/feature');
        }
        $this->get('/app/cro/features/progressive-gifts', $this->as($owner))->assertRedirectContains('/app/cro/progressive-gifts');
        $this->get('/app/cro/features/bundles', $this->as($owner))->assertRedirectContains('/app/cro/bundles');
        $this->get('/app/cro/features/nope', $this->as($owner))->assertNotFound();
    }

    public function test_builder_pages_and_permissions(): void
    {
        $this->fakeShopify();
        $store = $this->installedStore(['plan' => 'growth']);
        $owner = $this->member($store, 'owner');
        $staff = $this->member($store, 'staff');
        $other = $this->installedStore(['shop_domain' => 'other.myshopify.com']);

        $this->page('/app/cro', $owner)->assertOk()->assertJsonPath('component', 'cro/overview')->assertSee('Countdown timer');
        $this->page('/app/cro/experiences/new?type=countdown', $owner)->assertOk()->assertSee('Premium Card');
        $this->post('/app/cro/experiences', ['type' => 'countdown', 'template' => 'banner'], $this->as($owner))->assertRedirectContains('/edit');

        $experience = Experience::firstOrFail();
        $this->page("/app/cro/experiences/{$experience->id}/edit", $owner)->assertOk()->assertSee('Campaign ends');
        $this->page("/app/cro/experiences/{$experience->id}", $owner)->assertOk()->assertSee($experience->handle);
        $this->page('/app/cro/features/countdown', $owner)->assertOk()->assertJsonPath('props.experiences.0.name', $experience->name);
        $this->page('/app/cro/templates', $owner)->assertOk()->assertSee('Radial counter')->assertDontSee('Tier Cards');

        // Invalid publish re-renders the builder with the error; the draft is still saved.
        $config = $experience->draft_config;
        $config['content']['ends_at'] = '';
        $config['content']['headline'] = 'Weekend sale';
        $this->post("/app/cro/experiences/{$experience->id}", ['action' => 'publish', 'config' => $config], $this->as($owner))
            ->assertStatus(422)->assertSee('Set when the campaign ends.');
        $this->assertSame('Weekend sale', $experience->fresh()->draft_config['content']['headline']);
        $this->assertSame('draft', $experience->fresh()->status);

        $config['content']['ends_at'] = now()->addDays(3)->format('Y-m-d\TH:i');
        $this->post("/app/cro/experiences/{$experience->id}", ['action' => 'publish', 'config' => $config], $this->as($owner))
            ->assertRedirectContains('notice=published');
        $this->assertSame('published', $experience->fresh()->status);

        // Staff can build; nobody reaches another store's experience.
        $this->get("/app/cro/experiences/{$experience->id}/edit", $this->as($staff))->assertOk();
        $foreign = app(ExperienceManager::class)->create($other, 'trust', 'trust-row', null);
        $this->get("/app/cro/experiences/{$foreign->id}", $this->as($owner))->assertNotFound();
    }

    public function test_theme_callbacks_are_shipped_and_documented(): void
    {
        // The shared cart helper calls the theme's callbacks around every OrderOrbit add to cart.
        $js = file_get_contents(base_path('extensions/orderorbit-theme/assets/oo-commerce.js'));
        foreach (['OrderOrbitHooks', 'beforeAddToCart', 'afterAddToCart', 'addToCartFailed', 'orderorbit:', 'before-add', 'added-to-cart', 'add-failed'] as $needle) {
            $this->assertStringContainsString($needle, $js);
        }
        foreach (glob(base_path('extensions/orderorbit-theme/assets/*.js')) as $file) {
            $this->assertLessThan(10000, filesize($file), basename($file).' must stay under Shopify\'s 10 KB limit.');
        }

        // Cart upsells no longer place themselves in the cart drawer.
        $this->assertArrayNotHasKey('drawer', \App\Experiences\Registry::type('cart-upsells')['content']);

        config(['site.preview_password' => '']);
        $this->get('/help')->assertOk()
            ->assertSee('Developers: callbacks')->assertSee('How do I open my cart drawer after an add?')
            ->assertSee('window.OrderOrbitHooks = {', false)->assertSee('orderorbit:added-to-cart');
    }

    public function test_storefront_asset_is_served(): void
    {
        $this->get('/storefront/orderorbit.js')->assertOk()->assertHeader('Content-Type', 'application/javascript');
        $this->get('/storefront/oo-trust.js')->assertOk();
        $this->get('/storefront/oo-nope.js')->assertNotFound();
        $this->get('/storefront/../.env')->assertNotFound();
    }

    public function test_placement_is_rechecked_when_a_block_is_added_in_the_theme_editor(): void
    {
        $scans = 0;
        $store = $this->installedStore(['plan' => 'growth']);
        $owner = $this->member($store, 'owner');
        $experience = app(ExperienceManager::class)->create($store, 'trust', 'trust-row', null, 'Trust');
        $experience->forceFill(['status' => 'published', 'placement_status' => 'not_placed', 'placement_checked_at' => now()->subHour()])->save();
        $template = json_encode(['sections' => ['apps' => ['blocks' => ['b1' => ['type' => 'shopify://apps/orderorbit-space/blocks/experience/x', 'settings' => ['experience_type' => 'trust', 'experience_id' => $experience->handle]]]]]]);
        Http::fake(function (Request $request) use (&$scans, $template) {
            $query = $request['query'] ?? '';
            if (str_contains($query, 'themes(first: 1')) {
                $scans++;

                return Http::response(['data' => ['themes' => ['nodes' => [['id' => 'gid://shopify/OnlineStoreTheme/1']]]]]);
            }

            return str_contains($query, 'files(filenames')
                ? Http::response(['data' => ['theme' => ['files' => ['nodes' => [['filename' => 'templates/product.json', 'body' => ['content' => $template]]]]]]])
                : Http::response(['data' => []]);
        });

        // The merchant added the block in the Theme Editor; opening the experience finds it.
        $this->page('/app/cro/experiences/'.$experience->id, $owner)->assertOk()->assertJsonPath('props.experience.not_placed', false);
        $this->assertSame('placed', $experience->fresh()->placement_status);

        // Once everything is placed, pages don't scan the theme again.
        $this->page('/app/cro/experiences/'.$experience->id, $owner)->assertOk();
        $this->assertSame(1, $scans);
    }
}
