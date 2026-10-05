<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Services\Billing\PlanLimits;
use App\Services\Experiences\ExperienceManager;
use App\Services\Experiences\PublishException;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Experiences\TemplateLibrary;
use App\Services\Usage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** "Live widgets (all types)": a cap on everything live at once, on top of each type's own limit. */
class TotalWidgetLimitTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        Http::fake(fn ($request) => Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1'], 'metafieldsSet' => ['userErrors' => []]]]));
        app(TemplateLibrary::class)->sync();
    }

    private function publish($store, string $type, string $template): Experience
    {
        $manager = app(ExperienceManager::class);
        $experience = $manager->create($store->fresh(), $type, $template, null);
        $config = $experience->draft_config;
        $config['content']['products'] = [['id' => 'gid://shopify/Product/1', 'title' => 'Serum']];
        $config['content']['ship_date'] = now()->addDays(30)->toIso8601String();
        $manager->saveDraft($experience, \App\Experiences\Schema::normalize($type, $config)[0], [], null);
        $errors = \App\Experiences\Schema::normalize($type, $experience->fresh()->draft_config, 'UTC')[1];
        $this->assertSame([], $errors, "{$type} draft is valid");
        $manager->publish($experience->fresh(), null);

        return $experience->fresh();
    }

    public function test_the_total_caps_live_widgets_across_types(): void
    {
        $store = $this->installedStore(['plan' => 'free']); // Free: 4 in total, 1 of each type
        $this->publish($store, 'trust', 'trust-row');
        $this->publish($store, 'sticky-atc', 'simple-sticky-bar');
        $this->publish($store, 'sales-pop', 'classic-card');
        $this->publish($store, 'shipping-bar', 'minimal');
        $this->assertSame(4, app(Usage::class)->current($store, 'active_experiences'));

        // A fifth type that has room of its own (Free allows 1 bundle) is stopped by the total.
        $bundle = app(ExperienceManager::class)->create($store->fresh(), 'bundles', 'fx-classic', null);
        try {
            app(ExperienceManager::class)->publish($bundle, null);
            $this->fail('Free allows 4 live widgets in total.');
        } catch (PublishException $e) {
            $this->assertSame('The Free plan includes 4 live widgets in total. Pause or archive one that\'s live, or upgrade for more.', $e->getMessage());
        }

        // Set the total to 1 for this store: only one widget may run on the store at a time.
        $store->forceFill(['entitlements' => ['limits' => ['active_experiences' => 1]]])->save();
        $this->assertSame(3, app(PlanLimits::class)->apply($store->fresh()), 'The three oldest are paused; the newest stays live.');
        $this->assertSame(['shipping-bar'], collect(app(StorefrontPublisher::class)->payload($store->fresh())['experiences'])->pluck('type')->all());
        try {
            app(ExperienceManager::class)->resume(Experience::where('type', 'trust')->sole());
            $this->fail('Only one widget may be live.');
        } catch (PublishException $e) {
            $this->assertStringContainsString('includes 1 live widget in total', $e->getMessage());
        }

        // Empty total: only the per-type limits apply, and what the cap paused comes back.
        $store->forceFill(['entitlements' => ['limits' => ['active_experiences' => null]]])->save();
        $this->assertSame(3, app(PlanLimits::class)->apply($store->fresh()));
        $this->assertSame(4, app(Usage::class)->current($store->fresh(), 'active_experiences'));
        $this->publish($store, 'preorder', 'classic-card');
        $this->assertSame(5, app(Usage::class)->current($store->fresh(), 'active_experiences'));
    }
}
