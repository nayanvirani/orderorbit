<?php

namespace Tests\Unit;

use App\Experiences\Schema;
use App\Models\Experience;
use App\Services\Experiences\OfferSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferMappingTest extends TestCase
{
    use RefreshDatabase;

    private function offer(string $type, array $content, array $targeting = []): ?array
    {
        $config = Schema::defaults($type);
        $config['content'] = array_merge($config['content'], $content);
        $config['targeting'] = array_merge($config['targeting'], $targeting);

        return OfferSync::offer(new Experience(['type' => $type, 'handle' => 'exp-1']), $config);
    }

    private function products(int ...$ids): array
    {
        return array_map(fn ($id) => ['id' => "gid://shopify/Product/{$id}"], $ids);
    }

    public function test_quantity_breaks_keep_only_discounted_tiers_and_fall_back_to_targeted_products(): void
    {
        $offer = $this->offer('quantity-breaks', ['products' => []], ['products' => $this->products(5)]);

        $this->assertSame(['k' => 'tiers', 'p' => ['5'], 'tiers' => [[2, 10.0], [3, 20.0]], 'id' => 'exp-1', 'm' => 'Volume discount'], $offer);
    }

    public function test_bogo_maps_buy_and_get(): void
    {
        $offer = $this->offer('bogo', ['buy_products' => $this->products(7), 'get_products' => $this->products(8), 'buy_quantity' => 2, 'repeat' => false]);

        $this->assertSame(['7'], $offer['p']);
        $this->assertSame(['8'], $offer['g']);
        $this->assertSame([2, 1, 100.0, true], [$offer['bq'], $offer['gq'], $offer['v'], $offer['once']]);
    }

    public function test_upsells_gifts_and_shipping_only_create_offers_when_they_give_something(): void
    {
        $this->assertNull($this->offer('cart-upsells', ['products' => $this->products(1)]), 'No incentive, no discount.');
        $this->assertSame(10.0, $this->offer('product-upsells', ['products' => $this->products(1)])['v']);

        $this->assertNull($this->offer('free-gifts', ['gift_products' => []]));
        $this->assertSame([50.0, 100.0], $this->offer('free-gifts', ['gift_products' => $this->products(9)])['th']);

        $this->assertNull($this->offer('shipping-bar', []), 'Free shipping is opt-in.');
        $this->assertSame(['k' => 'ship', 'min' => 60.0, 'id' => 'exp-1', 'm' => 'Free shipping'], $this->offer('shipping-bar', ['free_shipping' => true]));
    }

    public function test_checkout_upsells_are_discounted_by_the_function(): void
    {
        $this->assertNull($this->offer('checkout-upsell', ['discount_percent' => 0]));
        $this->assertSame(['k' => 'upsell', 'v' => 15.0, 'id' => 'exp-1', 'm' => 'Checkout offer'], $this->offer('checkout-upsell', ['discount_percent' => 15]));
    }

    public function test_checkout_blocks_can_be_shown_as_coloured_banners(): void
    {
        $design = Schema::fields('checkout-countdown')['design'];
        $this->assertSame(['auto', 'banner', 'box', 'plain'], array_keys($design['ck_frame']['options']));
        $this->assertSame(['auto', 'info', 'success', 'warning', 'critical'], array_keys($design['ck_banner_tone']['options']));
        $this->assertArrayHasKey('cart-reservation', \App\Experiences\Registry::templates('checkout-countdown'));
        $this->assertArrayNotHasKey('payment_methods', Schema::fields('checkout-trust')['content']);
    }

    public function test_bundle_offers_map_one_product_offers_and_upsells(): void
    {
        $config = \App\Experiences\BundleSchema::defaults('qg-classic');
        $config['offers'][1]['gifts'][0]['product'] = $this->products(9);
        $config['upsells'] = ['enabled' => true, 'title' => 'Add', 'products' => $this->products(4), 'discount_percent' => 10];

        $offers = OfferSync::offersFor(new Experience(['type' => 'bundles', 'handle' => 'b-1']), $config);

        $this->assertSame('bq', $offers[0]['k']);
        $this->assertNull($offers[0]['o'][0]['v'] ?? null, 'The one-product tier at full price has no saving.');
        $this->assertSame(['q' => 2, 't' => 'percentage', 'v' => 10.0, 'g' => 1, 'gp' => ['9']], $offers[0]['o'][1]);
        $this->assertSame(['k' => 'upsell', 'id' => 'b-1:u', 'v' => 10.0, 'm' => 'Add'], $offers[1]);
    }

    public function test_pack_offers_are_left_to_the_cart_transform(): void
    {
        $config = \App\Experiences\BundleSchema::defaults('fx-classic');
        $config['offers'][1]['products'] = $this->products(1, 2);

        $offers = OfferSync::offersFor(new Experience(['type' => 'bundles', 'handle' => 'b-1']), $config);

        $this->assertSame([], $offers, 'The pack is priced by the cart transform and the single product has no saving, so no discount is needed.');
    }
}
