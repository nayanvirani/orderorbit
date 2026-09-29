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

    public function test_plan_limits_block_publishing(): void
    {
        $this->fakeShopify();
        $store = $this->installedStore(['plan' => 'starter']);
        $manager = app(ExperienceManager::class);

        $manager->publish($manager->create($store, 'shipping-bar', 'minimal', null), null);

        $this->expectException(PublishException::class);
        $manager->publish($manager->create($store, 'shipping-bar', 'progress', null), null);
    }

    public function test_cart_changing_types_stay_preview_only_for_now(): void
    {
        $this->fakeShopify();
        $store = $this->installedStore(['plan' => 'growth']);
        $manager = app(ExperienceManager::class);
        $experience = $manager->create($store, 'bundles', 'mix-and-match', null);

        try {
            $manager->publish($experience, null);
            $this->fail('Bundles should not publish yet.');
        } catch (PublishException $e) {
            $this->assertSame('unavailable', $e->reason);
        }
        Http::assertNothingSent();
    }

    public function test_no_plan_means_no_publishing(): void
    {
        $store = $this->installedStore();
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

    public function test_builder_pages_and_permissions(): void
    {
        $this->fakeShopify();
        $store = $this->installedStore(['plan' => 'growth']);
        $owner = $this->member($store, 'owner');
        $staff = $this->member($store, 'staff');
        $other = $this->installedStore(['shop_domain' => 'other.myshopify.com']);

        $this->get('/app/cro', $this->as($owner))->assertOk()->assertSee('Create an experience');
        $this->get('/app/cro/experiences/new?type=countdown', $this->as($owner))->assertOk()->assertSee('Premium Card');
        $this->post('/app/cro/experiences', ['type' => 'countdown', 'template' => 'banner'], $this->as($owner))->assertRedirectContains('/edit');

        $experience = Experience::firstOrFail();
        $this->get("/app/cro/experiences/{$experience->id}/edit", $this->as($owner))->assertOk()->assertSee('Campaign ends');
        $this->get("/app/cro/experiences/{$experience->id}", $this->as($owner))->assertOk()->assertSee($experience->handle);
        $this->get('/app/cro/countdown', $this->as($owner))->assertOk()->assertSee($experience->name);
        $this->get('/app/templates', $this->as($owner))->assertOk()->assertSee('Reward Ladder');

        // Invalid publish re-renders the builder with the error; the draft is still saved.
        $config = $experience->draft_config;
        $config['content']['ends_at'] = '';
        $config['content']['headline'] = 'Weekend sale';
        $this->post("/app/cro/experiences/{$experience->id}", ['action' => 'publish', 'config' => $config], $this->as($owner))
            ->assertOk()->assertSee('Campaign ends is required.');
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

    public function test_storefront_asset_is_served(): void
    {
        $this->get('/storefront/orderorbit.js')->assertOk()->assertHeader('Content-Type', 'application/javascript');
        $this->get('/storefront/../.env')->assertNotFound();
    }
}
