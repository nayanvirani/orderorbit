<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\FeatureFlag;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Experiences\TemplateLibrary;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** G37 step 1: a colour for each part of a storefront offer reaches the storefront as scoped CSS. */
class DesignTabTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        app(TemplateLibrary::class)->sync();
        Http::fake(fn () => Http::response(['data' => ['metafieldsSet' => ['userErrors' => []], 'currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1']]]));
    }

    public function test_part_colours_are_saved_and_published_as_scoped_css(): void
    {
        $store = $this->installedStore();
        $owner = $this->member($store, 'owner');
        $this->post('/app/cro/experiences', ['type' => 'countdown', 'template' => 'banner'], $this->as($owner))->assertRedirectContains('/edit');
        $this->post('/app/cro/experiences', ['type' => 'countdown', 'template' => 'banner'], $this->as($owner));
        [$timer, $other] = Experience::orderBy('id')->get()->all();

        // Without the flag the builder has no part colours.
        $this->page("/app/cro/experiences/{$timer->id}/edit", $owner)->assertJsonPath('props.elements', []);
        FeatureFlag::create(['key' => 'design_tab', 'mode' => 'all', 'enabled' => true]);
        Features::forget();
        $this->page("/app/cro/experiences/{$timer->id}/edit", $owner)
            ->assertJsonPath('props.elements.digits.label', 'Digits')
            ->assertJsonPath('props.layout.box_block.selector', '')
            ->assertJsonPath('props.brand.primary_color', '#303030')
            ->assertJsonPath('props.designSources.0.id', $other->id)
            ->assertJsonPath('props.designSources.0.same', true);

        $config = $timer->draft_config;
        $config['content']['ends_at'] = now()->addDays(3)->format('Y-m-d\TH:i');
        $config['design']['elements'] = ['digits' => '#FFD166', 'boxes' => 'red', 'nope' => '#000000', 'units' => '#abc'];
        $config['design']['layout'] = ['desktop' => ['title' => ['size' => 30]], 'mobile' => ['title' => ['size' => 20]]];
        $this->post("/app/cro/experiences/{$timer->id}", ['action' => 'publish', 'config' => $config], $this->as($owner))->assertRedirectContains('notice=published');
        $this->assertSame(['digits' => '#ffd166', 'units' => '#abc'], (array) $timer->fresh()->draft_config['design']['elements']);

        $shown = collect(app(StorefrontPublisher::class)->payload($store->fresh())['experiences'])->firstWhere('id', $timer->handle);
        $this->assertStringContainsString('.oo-u b{color:#ffd166!important}', $shown['design']['custom_css']);
        $this->assertStringContainsString('.oo-u small{color:#abc!important}', $shown['design']['custom_css']);
        $this->assertStringContainsString('@media (max-width: 749px){[data-oo-exp="'.$timer->handle.'"] .oo-title{font-size:20px!important}}', $shown['design']['custom_css']);
        $this->assertArrayNotHasKey('elements', $shown['design']);
        $this->assertArrayNotHasKey('layout', $shown['design']);
    }
}
