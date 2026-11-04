<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** The new menu (flag new_menu): new names and Create an offer; same routes and pages. */
class NewMenuTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
    }

    public function test_names_change_only_with_the_flag(): void
    {
        $store = $this->installedStore();
        $owner = $this->member($store, 'owner');

        $this->page('/app/cro', $owner)->assertJsonPath('shared.nav.label', 'CRO')->assertJsonPath('shared.newMenu', false)->assertSee('Progressive gifts');
        $this->page('/app/cro/create', $owner)->assertNotFound();

        FeatureFlag::create(['key' => 'new_menu', 'mode' => 'all', 'enabled' => true]);
        Features::forget();

        $this->page('/app/cro', $owner)->assertJsonPath('shared.nav.label', 'Offers')->assertJsonPath('shared.newMenu', true)
            ->assertJsonPath('shared.nav.groups.0.items.0.label', 'Offers overview')
            ->assertJsonPath('shared.nav.groups.0.items.1.label', 'Create an offer')
            ->assertSee('Spend and unlock rewards')->assertDontSee('Progressive gifts');
        $this->page('/app/analytics', $owner)->assertJsonPath('shared.nav.label', 'Insights')->assertJsonPath('shared.nav.groups.0.items.0.label', 'Widget results');
        $this->page('/app/experiments', $owner)->assertJsonPath('shared.nav.label', 'Audiences and tests')
            ->assertJsonPath('shared.nav.groups.0.heading', 'A/B tests')->assertJsonPath('shared.nav.groups.1.heading', 'Audiences');

        $page = $this->page('/app/cro/create', $owner)->assertOk()->assertJsonPath('component', 'cro/catalog')
            ->assertJsonPath('props.steps.0.name', 'Product page')
            ->assertJsonPath('props.steps.0.offers.0.label', 'Bundles and quantity discounts')
            ->assertJsonPath('props.steps.0.offers.0.was', 'Was: Bundles');
        $steps = collect($page->json('props.steps'));
        $this->assertSame(['product', 'cart', 'store', 'checkout', 'after'], $steps->pluck('key')->all());
        $kinds = $steps->flatMap(fn ($s) => $s['offers'])->flatMap(fn ($o) => $o['kinds']);
        $this->assertNotEmpty($kinds);
        foreach ($kinds as $kind) {
            $this->assertStringStartsWith('/app/cro/', $kind['href']);
        }
    }
}
