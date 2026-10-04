<?php

namespace Tests\Feature;

use App\Experiences\Schema;
use App\Models\AnalyticsEvent;
use App\Services\Experiences\ExperienceManager;
use App\Services\Experiences\TemplateLibrary;
use App\Support\Jwt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class PostPurchaseTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        app(TemplateLibrary::class)->sync();
        Http::fake(fn (Request $request) => match (true) {
            str_contains($request['query'] ?? '', 'product(id: $id)') => Http::response(['data' => ['product' => [
                'title' => str_contains(json_encode($request['variables'] ?? []), '/71') ? 'Travel kit' : 'Lip balm',
                'featuredMedia' => ['preview' => ['image' => ['url' => 'https://cdn.shopify.com/offer.jpg']]],
                'variants' => ['nodes' => [
                    ['id' => 'gid://shopify/ProductVariant/700', 'title' => 'Sold out', 'price' => '30.00', 'availableForSale' => false],
                    ['id' => 'gid://shopify/ProductVariant/701', 'title' => 'Default Title', 'price' => '40.00', 'availableForSale' => true],
                ]],
            ]]]),
            str_contains($request['query'] ?? '', 'currentAppInstallation { id }') => Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1'], 'shop' => ['id' => 'gid://shopify/Shop/1']]]),
            str_contains($request['query'] ?? '', 'metafieldsSet') => Http::response(['data' => ['metafieldsSet' => ['userErrors' => []]]]),
            default => Http::response(['data' => []]),
        });
    }

    /** The token Shopify signs for a purchase. */
    private function purchaseToken(array $products, float $total, string $ref = 'ref-123'): string
    {
        return Jwt::encode([
            'iss' => 'shopify', 'dest' => 'https://'.$this->shop, 'exp' => time() + 600,
            'input_data' => ['initialPurchase' => [
                'referenceId' => $ref,
                'totalPriceSet' => ['shopMoney' => ['amount' => (string) $total, 'currencyCode' => 'USD']],
                'lineItems' => array_map(fn ($id) => ['product' => ['id' => $id]], $products),
            ]],
        ], (string) config('shopify.api_secret'));
    }

    private function funnel(array $content, string $template = 'offer-with-downsell')
    {
        $manager = app(ExperienceManager::class);
        $store = \App\Models\Store::where('shop_domain', $this->shop)->first();
        $experience = $manager->create($store, 'post-purchase', $template, null);
        $config = $experience->draft_config;
        $config['content'] = array_merge($config['content'], $content);
        [$clean, $errors] = Schema::normalize('post-purchase', $config);
        $this->assertSame([], $errors);
        $manager->saveDraft($experience, $clean, [], null);
        $manager->publish($experience->fresh(), null);

        return $experience->fresh();
    }

    private function offer(string $token)
    {
        return $this->postJson('/api/post-purchase/offer', ['token' => $token, 'shop' => $this->shop]);
    }

    public function test_the_funnel_offers_and_signs_from_server_side_data(): void
    {
        $this->installedStore(['plan' => 'growth']);
        $funnel = $this->funnel([
            'trigger' => 'min_total', 'min_total' => 50,
            'offer_product' => [['id' => 'gid://shopify/Product/70', 'title' => 'Lip balm']], 'discount_percent' => 15,
            'downsell' => true, 'downsell_product' => [['id' => 'gid://shopify/Product/71', 'title' => 'Travel kit']], 'downsell_discount' => 25,
        ]);

        // Below the trigger, or with a bad token: no offer.
        $this->offer($this->purchaseToken(['gid://shopify/Product/1'], 40))->assertOk()->assertJson(['funnel' => null]);
        $this->offer('not.a.token')->assertOk()->assertJson(['funnel' => null]);
        $forged = Jwt::encode(['input_data' => []], 'someone-elses-secret');
        $this->offer($forged)->assertOk()->assertJson(['funnel' => null]);

        // Above it: the offer and the second offer, with live variants (the sold-out one skipped).
        $res = $this->offer($this->purchaseToken(['gid://shopify/Product/1'], 80))->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*')->json('funnel');
        $this->assertSame($funnel->handle, $res['experience_id']);
        $this->assertEquals(['701', 'Lip balm', 15, 40], [$res['offers'][0]['variant_id'], $res['offers'][0]['product_title'], $res['offers'][0]['discount_percent'], $res['offers'][0]['price']]);
        $this->assertSame(['Travel kit', 25, 'How about this instead?'], [$res['offers'][1]['product_title'], $res['offers'][1]['discount_percent'], $res['offers'][1]['headline']]);

        // A product already in the order isn't offered again.
        $already = $this->offer($this->purchaseToken(['gid://shopify/Product/70'], 80))->json('funnel');
        $this->assertCount(1, $already['offers']);
        $this->assertSame('Travel kit', $already['offers'][0]['product_title']);

        // Accepting: the change is built here; the reference comes from Shopify's token.
        $signed = $this->postJson('/api/post-purchase/sign', [
            'token' => $this->purchaseToken(['gid://shopify/Product/1'], 80, 'ref-999'), 'shop' => $this->shop,
            'experience_id' => $funnel->handle, 'step' => 1, 'reference_id' => 'tampered',
        ])->assertOk()->json('token');
        $claims = Jwt::decode($signed, (string) config('shopify.api_secret'));
        $this->assertSame('ref-999', $claims['sub']);
        $this->assertSame(config('shopify.api_key'), $claims['iss']);
        $this->assertSame([['type' => 'add_variant', 'variantId' => 701, 'quantity' => 1, 'discount' => ['value' => 25, 'valueType' => 'percentage', 'title' => '25% off']]], $claims['changes']);
        $this->assertGreaterThan(time() * 100, $claims['iat'], 'iat in milliseconds, as Shopify signs it');

        // An offer that isn't part of the funnel can't be signed.
        $this->postJson('/api/post-purchase/sign', ['token' => $this->purchaseToken(['gid://shopify/Product/1'], 80), 'shop' => $this->shop, 'experience_id' => 'other', 'step' => 0])->assertStatus(422);
        $this->postJson('/api/post-purchase/sign', ['token' => $this->purchaseToken(['gid://shopify/Product/1'], 80), 'shop' => $this->shop, 'experience_id' => $funnel->handle, 'step' => 5])->assertStatus(422);

        $this->postJson('/api/post-purchase/decline', ['token' => $this->purchaseToken(['gid://shopify/Product/1'], 80), 'shop' => $this->shop, 'experience_id' => $funnel->handle])->assertOk();

        $events = AnalyticsEvent::where('experience_handle', $funnel->handle)->get()->groupBy('event')->map->count()->all();
        $this->assertSame(['view' => 2, 'accept' => 1, 'attributed' => 1, 'decline' => 1], array_intersect_key($events, array_flip(['view', 'accept', 'attributed', 'decline'])));
        $this->assertEquals(30.0, AnalyticsEvent::where('event', 'attributed')->value('value'), '$40 less 25%');
    }

    public function test_product_triggers_and_plan_gating(): void
    {
        $store = $this->installedStore(['plan' => 'growth']);
        $this->funnel([
            'trigger' => 'products', 'trigger_products' => [['id' => 'gid://shopify/Product/5', 'title' => 'Serum']],
            'offer_product' => [['id' => 'gid://shopify/Product/70', 'title' => 'Lip balm']],
        ], 'classic-offer');

        $this->assertNull($this->offer($this->purchaseToken(['gid://shopify/Product/9'], 100))->json('funnel'));
        $this->assertNotNull($this->offer($this->purchaseToken(['gid://shopify/Product/5'], 100))->json('funnel'));

        $store->forceFill(['plan' => 'starter'])->save();
        $this->assertNull($this->offer($this->purchaseToken(['gid://shopify/Product/5'], 100))->json('funnel'), 'Growth and above only.');
    }

    public function test_validation_and_builder(): void
    {
        $errors = Schema::normalize('post-purchase', array_replace_recursive(Schema::defaults('post-purchase'), ['content' => ['downsell' => true, 'offer_product' => [['id' => 'gid://shopify/Product/1', 'title' => 'A']]]]))[1];
        $this->assertSame('Choose the second offer, or turn the second offer off.', $errors['content.downsell_product']);

        // Checkout countdown: hour and minute timers don't need a date.
        $cd = Schema::defaults('checkout-countdown');
        $cd['content']['mode'] = 'minutes';
        $cd['content']['minutes'] = 10;
        $this->assertSame([], Schema::normalize('checkout-countdown', $cd)[1]);
        $this->assertSame('restart', Schema::normalize('checkout-countdown', $cd)[0]['content']['repeat']);

        $owner = $this->member($this->installedStore(['plan' => 'growth']), 'owner');
        $this->get('/app/cro/features/post-purchase', $this->as($owner))->assertOk()->assertSee('Open checkout settings')->assertSee('Create post-purchase funnel');
        $this->post('/app/cro/experiences', ['type' => 'post-purchase', 'template' => 'classic-offer'], $this->as($owner))->assertRedirectContains('/edit');
        $this->get('/app/cro/experiences/1/edit', $this->as($owner))->assertOk()
            ->assertSee('Offer something else if they decline')->assertSee('choose <strong>OrderOrbit Space</strong> under Post-purchase page', false);
    }
}
