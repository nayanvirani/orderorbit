<?php

namespace Tests\Feature;

use App\Experiences\GiftSchema;
use App\Models\Experience;
use App\Services\Experiences\TemplateLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class GiftModuleTest extends TestCase
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
                str_contains($query, 'discountAutomaticAppCreate') => Http::response(['data' => ['discountAutomaticAppCreate' => ['automaticAppDiscount' => ['discountId' => 'gid://shopify/DiscountAutomaticNode/88'], 'userErrors' => []]]]),
                str_contains($query, 'discountAutomaticDelete') => Http::response(['data' => ['discountAutomaticDelete' => ['userErrors' => []]]]),
                str_contains($query, 'currentAppInstallation { id }') => Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1']]]),
                str_contains($query, 'metafieldsSet') => Http::response(['data' => ['metafieldsSet' => ['userErrors' => []]]]),
                default => Http::response(['data' => []]),
            };
        });
    }

    public function test_screens_create_and_edit(): void
    {
        $store = $this->installedStore();
        $owner = $this->member($store, 'owner');

        $this->page('/app/cro/progressive-gifts', $owner)->assertOk()->assertJsonPath('props.items', []);
        $this->page('/app/cro/progressive-gifts/new', $owner)->assertOk()
            ->assertSee('Classic')->assertSee('Expressive')->assertSee('Minimal strip')->assertSee('Radial counter');
        $this->post('/app/cro/progressive-gifts', ['model' => 'pg-steps'], $this->as($owner))->assertRedirectContains('/app/cro/progressive-gifts/');

        $gift = Experience::where('type', 'progressive-gifts')->firstOrFail();
        $this->assertSame('steps', $gift->draft_config['settings']['layout']);
        $this->page("/app/cro/progressive-gifts/{$gift->id}", $owner)->assertOk()->assertJsonPath('component', 'gifts/editor');
        $this->get("/app/cro/experiences/{$gift->id}/edit", $this->as($owner))->assertRedirectContains("/app/cro/progressive-gifts/{$gift->id}");
        $this->page('/app/cro/progressive-gifts', $owner)->assertJsonPath('props.items.0.rewards', 'Free gift · Free shipping · Choose your gift');
    }

    public function test_validate_then_publish_with_the_right_discount_classes(): void
    {
        $store = $this->installedStore();
        $owner = $this->member($store, 'owner');
        $this->post('/app/cro/progressive-gifts', ['model' => 'pg-classic'], $this->as($owner));
        $gift = Experience::firstOrFail();

        // Gift rewards without a product can't publish.
        $config = $gift->draft_config;
        $this->post("/app/cro/progressive-gifts/{$gift->id}", ['config_json' => json_encode($config), 'action' => 'publish'], $this->as($owner))
            ->assertStatus(422)->assertSee('Fix the highlighted settings before publishing.');

        $config['milestones'][0]['products'] = [['id' => 'gid://shopify/Product/9', 'title' => 'Tote', 'variant_id' => 'gid://shopify/ProductVariant/90']];
        $config['milestones'][2] = GiftSchema::milestone(120, 'percent', '10% off', 10);
        $this->post("/app/cro/progressive-gifts/{$gift->id}", ['config_json' => json_encode($config), 'action' => 'publish'], $this->as($owner))
            ->assertRedirectContains('notice=published');

        $this->assertSame('gid://shopify/DiscountAutomaticNode/88', $gift->fresh()->shopify_discount_id);
        Http::assertSent(function (Request $r) {
            if (! str_contains($r['query'] ?? '', 'discountAutomaticAppCreate')) {
                return false;
            }
            $discount = json_decode($r->body(), true)['variables']['discount'];
            $offer = json_decode($discount['metafields'][0]['value'], true)['offers'][0];

            return $discount['discountClasses'] === ['PRODUCT', 'ORDER', 'SHIPPING']
                && is_string($discount['title']) && $discount['title'] === $offer['n']
                && $offer['k'] === 'pg' && $offer['by'] === 'value'
                && $offer['m'] === [['t' => 50, 'r' => 'gift', 'q' => 1], ['t' => 75, 'r' => 'shipping'], ['t' => 120, 'r' => 'percent', 'v' => 10]];
        });
    }

    public function test_payload_and_legacy_migration(): void
    {
        $config = GiftSchema::defaults('pg-radial');
        $config['settings']['placement'] = 'cart';
        $payload = GiftSchema::payload(GiftSchema::normalize($config)[0]);
        $this->assertSame(['cart'], $payload['targeting']['page_types']);
        $this->assertSame('block', $payload['behavior']['position'], 'The cart page shows it where the block is placed.');
        $this->assertSame([0, 1, 2], array_column($payload['content']['milestones'], 'index'));

        [$migrated] = GiftSchema::normalize(['content' => ['thresholds' => [['amount' => 60, 'reward' => 'free shipping']]]]);
        $this->assertSame('shipping', $migrated['milestones'][0]['reward']);
        $this->assertEquals(60, $migrated['milestones'][0]['threshold']);
    }
}
