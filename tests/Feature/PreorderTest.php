<?php

namespace Tests\Feature;

use App\Experiences\Schema;
use App\Services\Experiences\ExperienceManager;
use App\Services\Experiences\PublishException;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Experiences\TemplateLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class PreorderTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        app(TemplateLibrary::class)->sync();
        Http::fake(fn (Request $request) => match (true) {
            str_contains($request['query'] ?? '', 'currentAppInstallation { id }') => Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1']]]),
            str_contains($request['query'] ?? '', 'metafieldsSet') => Http::response(['data' => ['metafieldsSet' => ['userErrors' => []]]]),
            default => Http::response(['data' => []]),
        });
    }

    public function test_preorder_needs_products_and_a_ship_date(): void
    {
        $defaults = Schema::defaults('preorder');
        [, $errors] = Schema::normalize('preorder', $defaults);
        $this->assertArrayHasKey('content.products', $errors);
        $this->assertSame('Set the expected ship date.', $errors['content.ship_date']);

        $config = $defaults;
        $config['content']['products'] = [['id' => 'gid://shopify/Product/55', 'title' => 'Jacket']];
        $config['content']['ship_date'] = now()->addDays(30)->format('Y-m-d\TH:i');
        $config['content']['start_date'] = now()->addDays(40)->format('Y-m-d\TH:i');
        [, $errors] = Schema::normalize('preorder', $config);
        $this->assertSame(['content.start_date' => 'The pre-order must open before the ship date.'], $errors);

        // Ships a number of days after the order: no date needed.
        $config['content']['ship_mode'] = 'relative';
        $config['content']['start_date'] = null;
        $config['content']['ship_date'] = null;
        $this->assertSame([], Schema::normalize('preorder', $config)[1]);
    }

    public function test_preorder_publishes_to_the_storefront(): void
    {
        $store = $this->installedStore(['plan' => 'growth']);
        $owner = $this->member($store, 'owner');
        $manager = app(ExperienceManager::class);

        $experience = $manager->create($store, 'preorder', 'countdown-tiles', $owner);
        $this->assertSame('months', $experience->draft_config['content']['breakdown'], 'The template presets its breakdown.');

        try {
            $manager->publish($experience, $owner);
            $this->fail('A pre-order without products or a ship date must not publish.');
        } catch (PublishException) {
        }

        $config = $experience->draft_config;
        $config['content']['products'] = [['id' => 'gid://shopify/Product/55', 'title' => 'Jacket']];
        $config['content']['ship_date'] = now()->addDays(45)->toIso8601String();
        $manager->saveDraft($experience, Schema::normalize('preorder', $config)[0], [], $owner);
        $manager->publish($experience->fresh(), $owner);

        $live = collect(app(StorefrontPublisher::class)->payload($store)['experiences'])->firstWhere('type', 'preorder');
        $this->assertSame('tiles', $live['style']);
        $this->assertSame('Pre-order now', $live['content']['button_text']);
        $this->assertSame('gid://shopify/Product/55', $live['content']['products'][0]['id']);
    }

    public function test_preorder_pages(): void
    {
        $store = $this->installedStore(['plan' => 'growth']);
        $owner = $this->member($store, 'owner');

        $feature = $this->page('/app/cro/features/preorder', $owner)->assertOk()->assertJsonPath('props.editor.label', 'Open Theme Editor');
        $this->assertStringContainsString('template=product', $feature->json('props.editor.url'));
        $this->page('/app/cro/experiences/new?type=preorder', $owner)->assertOk()
            ->assertSee('Classic card')->assertSee('Timeline steps')->assertSee('Countdown tiles')->assertSee('Goal tracker')->assertSee('Badge pill');
        $this->post('/app/cro/experiences', ['type' => 'preorder', 'template' => 'timeline'], $this->as($owner))->assertRedirectContains('/edit');
        $this->page('/app/cro/experiences/1/edit', $owner)->assertOk()
            ->assertSee('Pre-order products')->assertSee('Time left shown as')->assertSee('Units reserved toward a goal')->assertSee('Mark pre-order items in the cart and order');
    }
}
