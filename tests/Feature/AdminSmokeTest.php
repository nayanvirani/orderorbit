<?php

namespace Tests\Feature;

use App\Experiences\Registry;
use App\Models\Experience;
use App\Services\Experiences\ExperienceManager;
use App\Services\Experiences\TemplateLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/**
 * Opens every admin screen and experience tab for every experience type (new, old and
 * migrated configs) and fails on any server error.
 */
class AdminSmokeTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    public function test_every_admin_screen_renders(): void
    {
        $this->setUpShopify();
        app(TemplateLibrary::class)->sync();
        Http::fake(fn () => Http::response(['data' => []]));
        $store = $this->installedStore(['goal' => 'aov']);
        $owner = $this->member($store, 'owner');
        $manager = app(ExperienceManager::class);

        foreach (array_keys(Registry::types()) as $type) {
            $manager->create($store, $type, array_key_first(Registry::templates($type)), null);
        }
        // A bundle saved before the Bundles module (flat content).
        Experience::create(['store_id' => $store->id, 'type' => 'bundles', 'name' => 'Legacy', 'template_key' => 'mix-and-match', 'status' => 'draft',
            'draft_config' => ['content' => ['bundle_mode' => 'mix', 'products' => [['id' => 'gid://shopify/Product/1', 'title' => 'A']]], 'analytics' => []]]);

        $pages = ['/app', '/app/onboarding', '/app/cro', '/app/cro/experiences', '/app/cro/experiences/new', '/app/cro/templates',
            '/app/cro/bundles', '/app/cro/bundles/new', '/app/cro/progressive-gifts', '/app/cro/progressive-gifts/new',
            '/app/settings/store', '/app/settings/users', '/app/settings/branding', '/app/settings/billing', '/app/settings/activity'];
        foreach (array_keys(Registry::features()) as $feature) {
            $pages[] = "/app/cro/features/{$feature}";
        }
        foreach (Experience::all() as $e) {
            foreach (['overview', 'configuration', 'targeting', 'analytics', 'experiment', 'history'] as $tab) {
                $pages[] = "/app/cro/experiences/{$e->id}?tab={$tab}";
            }
            $pages[] = "/app/cro/experiences/{$e->id}/edit";
        }

        foreach ($pages as $url) {
            $status = $this->get($url, $this->as($owner))->getStatusCode();
            $this->assertLessThan(500, $status, "{$url} returned {$status}");
        }
    }
}
