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

    public function test_checkout_blocks_never_use_shopify_banners_with_their_status_icon(): void
    {
        $design = Schema::fields('checkout-countdown')['design'];
        $this->assertSame(['auto', 'box', 'plain'], array_keys($design['ck_frame']['options']));
        $this->assertArrayNotHasKey('ck_banner_tone', $design);
        foreach (array_merge(glob(base_path('extensions/orderorbit-checkout/src/*.jsx')), glob(base_path('extensions/orderorbit-account/src/*.jsx'))) as $file) {
            $this->assertStringNotContainsString('<s-banner', file_get_contents($file), basename(dirname($file, 2)).' must not draw a Shopify banner.');
        }
        // "Cart reservation" claimed a reservation Growvia doesn't make (requirement 1.1.4).
        $this->assertArrayNotHasKey('cart-reservation', \App\Experiences\Registry::templates('checkout-countdown'));
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

    public function test_tiered_gift_bundles_are_on_for_new_bundles_off_for_saved_ones_and_give_free_shipping(): void
    {
        $schema = \App\Experiences\BundleSchema::class;
        $new = $schema::defaults('qg-tiered');
        $this->assertTrue($new['tiers']['details'] && $new['tiers']['sale_header'] && $new['tiers']['button_price']);
        $this->assertSame('berry', $new['design']['preset']);

        // A bundle saved before G36 has no tier options: it looks exactly as before.
        $old = $schema::defaults('qg-classic');
        unset($old['tiers']);
        [$saved] = $schema::normalize($old);
        $this->assertFalse($saved['tiers']['details'] || $saved['tiers']['pictures'] || $saved['tiers']['gifts_row'] || $saved['tiers']['delivery']['enabled']);
        $this->assertFalse($schema::payload($saved)['content']['tiers']['on'], 'The storefront doesn\'t load the tier options.');
        $this->assertTrue($schema::payload($schema::normalize($new)[0])['content']['tiers']['on']);

        // A tier can mix products, and each item and gift can carry a description and value.
        $new['offers'][2] = ['kind' => 'multi', 'title' => 'Buy 3, get 2 free gifts', 'products' => [$this->products(1)[0] + ['quantity' => 2], $this->products(2)[0]],
            'notes' => [['desc' => 'Brightening daily serum', 'value' => '48'], ['desc' => '', 'value' => '']], 'label' => 'Best value', 'label_style' => 'outlined',
            'gifts' => [['product' => $this->products(9), 'quantity' => 1, 'desc' => 'Tinted', 'value' => 14]], 'discount_type' => 'percentage', 'discount_value' => 20];
        $new['tiers']['free_shipping'] = $new['offers'][1]['id'];
        $new['tiers']['delivery'] = ['enabled' => true, 'text' => 'Arrives soon', 'min_days' => 5, 'max_days' => 3];
        [$config, $errors] = $schema::normalize($new, 'Asia/Kolkata');
        $this->assertSame(['tiers.delivery.max_days', 'tiers.delivery.text'], array_keys(array_filter($errors, fn ($k) => str_starts_with($k, 'tiers'), ARRAY_FILTER_USE_KEY)));
        $this->assertSame('multi', $config['offers'][2]['kind']);
        $this->assertSame([['name' => '', 'desc' => 'Brightening daily serum', 'value' => 48.0], ['name' => '', 'desc' => '', 'value' => null]], $config['offers'][2]['notes']);
        $this->assertSame(['Tinted', 14.0, 'outlined'], [$config['offers'][2]['gifts'][0]['desc'], $config['offers'][2]['gifts'][0]['value'], $config['offers'][2]['label_style']]);
        $this->assertSame('Asia/Kolkata', $schema::normalize($config)[0]['tiers']['delivery']['timezone'], 'Re-checks keep the store\'s time zone.');

        // Free shipping from the second tier on: the units each tier must hold at checkout.
        $config['offers'][1]['gifts'][0]['product'] = $this->products(8);
        $offers = OfferSync::offersFor(new Experience(['type' => 'bundles', 'handle' => 'b-1']), $config);
        $this->assertSame(['k' => 'bship', 'id' => 'b-1', 't' => ['1' => 2, '2' => 3], 'm' => 'Free shipping'], collect($offers)->firstWhere('k', 'bship'));
    }

    public function test_the_tiered_gift_template_only_needs_the_gift_products(): void
    {
        $schema = \App\Experiences\BundleSchema::class;
        $c = $schema::defaults('qg-tiered');
        $this->assertSame(['Buy One', 'Buy 2, Get 1 Free Gift', 'Buy 3, Get 2 Free Gifts'], array_column($c['offers'], 'title'));
        $this->assertSame([['Most Popular', 'filled'], ['Best Value', 'outlined']], [[$c['offers'][1]['label'], $c['offers'][1]['label_style']], [$c['offers'][2]['label'], $c['offers'][2]['label_style']]]);
        $this->assertSame(['tier1', true, true], [$c['tiers']['free_shipping'], $c['tiers']['delivery']['enabled'], $c['offers'][2]['preselected']]);

        // What the merchant picks: the product page, the mix in Buy 3 and the gifts. Shoppers pick nothing.
        $this->assertSame(['multi', false], [$c['offers'][2]['kind'], $c['settings']['show_variants']]);
        $this->assertSame(['settings.products', 'offers.1.gifts.0.product', 'offers.2.products', 'offers.2.gifts.0.product', 'offers.2.gifts.1.product'], array_keys($schema::normalize($c)[1]));
        $c['settings']['products'] = $this->products(1);
        $c['offers'][2]['products'] = [$this->products(1)[0] + ['quantity' => 2], $this->products(2)[0]];
        $c['offers'][2]['notes'][1]['name'] = 'Night Cream 50 ml';
        $c['offers'][1]['gifts'][0]['product'] = $c['offers'][2]['gifts'][0]['product'] = $this->products(9);
        $c['offers'][2]['gifts'][1]['product'] = $this->products(8);
        [$ready, $errors] = $schema::normalize($c);
        $this->assertSame([], $errors);
        $this->assertSame('Night Cream 50 ml', $ready['offers'][2]['notes'][1]['name']);
        $this->assertSame('bship', collect(OfferSync::offersFor(new Experience(['type' => 'bundles', 'handle' => 'b-1']), $ready))->last()['k']);
    }

    public function test_mix_and_match_quantity_tiers_validate_and_price_at_checkout(): void
    {
        $schema = \App\Experiences\BundleSchema::class;
        $c = $schema::defaults('mt-grid');
        $this->assertSame(['mix-tiers', 'free', [3, 5]], [$c['bundle_type'], $c['mt']['rule'], array_column($c['mt']['tiers'], 'count')]);
        $this->assertSame(['mt.pool'], array_keys($schema::normalize($c)[1]), 'Only the products are left to pick; no offers are needed.');

        $c['mt']['pool'] = [['id' => 'gid://shopify/Product/1'], ['id' => 'gid://shopify/Product/2']];
        $c['mt']['tiers'][] = ['count' => 4, 'value' => 4];
        $this->assertSame(['mt.tiers.1.value'], array_keys($schema::normalize($c)[1]), '4 free in a set of 4 is not allowed (tiers sort by size).');
        array_pop($c['mt']['tiers']);
        [$ready, $errors] = $schema::normalize($c);
        $this->assertSame([], $errors);
        $this->assertFalse(\App\Services\Experiences\BundleSync::merges($ready), 'Lines stay separate: the discount prices them.');
        $this->assertSame([['k' => 'mt', 'id' => 'b-9', 'r' => 'free', 't' => [[3, 1.0], [5, 2.0]], 'p' => ['1', '2'], 'm' => 'BUNDLE & SAVE']],
            OfferSync::offersFor(new Experience(['type' => 'bundles', 'handle' => 'b-9']), $ready));
        $this->assertSame('free', $schema::payload($ready)['content']['mt']['rule']);
        $this->assertNull($schema::payload($schema::normalize($schema::defaults('qb-classic'))[0])['content']['mt'], 'Other bundles don\'t carry it.');
    }

    public function test_every_text_and_detail_of_the_new_bundles_can_be_changed_or_hidden(): void
    {
        $schema = \App\Experiences\BundleSchema::class;
        // Saved before these settings: today's wording and everything shown.
        $old = $schema::defaults('qg-tiered');
        unset($old['tiers']['words'], $old['tiers']['show_values'], $old['mt']);
        [$c] = $schema::normalize($old);
        $this->assertSame($schema::TIER_WORDS, $c['tiers']['words']);
        $this->assertTrue($c['tiers']['show_values'] && $c['tiers']['show_desc'] && $c['mt']['show_tiers'] && $c['mt']['show_price'] && $c['mt']['button_price']);

        // An emptied text stays empty (hidden); a changed one is kept.
        $c['tiers']['words']['plus'] = '';
        $c['tiers']['words']['free'] = 'GRATIS';
        $c['tiers']['show_desc'] = false;
        $c['mt']['empty'] = '';
        $c['mt']['show_nudge'] = false;
        [$saved] = $schema::normalize($c);
        $this->assertSame(['', 'GRATIS', false, '', false], [$saved['tiers']['words']['plus'], $saved['tiers']['words']['free'], $saved['tiers']['show_desc'], $saved['mt']['empty'], $saved['mt']['show_nudge']]);
    }
}
