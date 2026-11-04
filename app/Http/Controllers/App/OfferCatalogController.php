<?php

namespace App\Http\Controllers\App;

use App\Experiences\BundleSchema;
use App\Experiences\Registry;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Support\Features;
use App\Support\Spa\Page;

/**
 * Offers › Create an offer (new menu): every offer grouped by the step of the shopper's visit, each
 * explained with its kinds, linking to today's create pages. Labels only: nothing new is created here.
 */
class OfferCatalogController extends Controller
{
    /** step => [name, goal, [feature key => where it shows]] */
    private const STEPS = [
        'product' => ['Product page', 'Bigger orders', [
            'bundles' => 'Product page, below the price', 'countdown' => 'Product page, cart or any page',
            'sticky-atc' => 'Product page, top or bottom of the screen', 'preorder' => 'Product page, replaces the buy button', 'trust' => 'Product page or cart',
        ]],
        'cart' => ['Cart', 'Spend a little more', ['progressive-gifts' => 'Cart, cart drawer and product page', 'cart-upsells' => 'Cart and cart drawer']],
        'store' => ['Whole store', 'Build trust', ['sales-pop' => 'Any page, bottom corner']],
        'checkout' => ['Checkout', 'Finish the order', ['checkout' => 'Shopify checkout']],
        'after' => ['After purchase', 'Come back and buy again', [
            'post-purchase' => 'Between payment and the Thank You page', 'thank-you' => 'Thank You and order status pages', 'customer-accounts' => 'Customer account pages',
        ]],
    ];

    public function __invoke(Store $store): Page
    {
        abort_unless(Registry::newMenu(), 404);
        $features = Registry::features();

        $steps = [];
        foreach (self::STEPS as $key => [$name, $goal, $offers]) {
            $steps[] = ['key' => $key, 'name' => $name, 'goal' => $goal, 'offers' => collect($offers)
                ->filter(fn ($where, $feature) => isset($features[$feature]))
                ->map(fn ($where, $feature) => [
                    'key' => $feature, 'label' => $features[$feature]['label'], 'icon' => $features[$feature]['icon'] ?? 'sparkle', 'tone' => $features[$feature]['tone'] ?? 'default',
                    'lead' => strip_tags($features[$feature]['lead']), 'where' => $where,
                    'was' => isset(Registry::NEW_NAMES[$feature]) ? 'Was: '.Registry::oldLabel($feature) : null,
                    'kinds' => $this->kinds($store, $feature, $features[$feature]),
                ])->values()->all()];
        }

        return page('cro/catalog', ['steps' => $steps]);
    }

    /** The kinds of an offer, each with its create link. */
    private function kinds(Store $store, string $key, array $feature): array
    {
        if ($key === 'bundles') {
            return collect(BundleSchema::TYPES)->filter(fn ($t) => empty($t['flag']) || Features::enabled($t['flag'], $store))
                ->map(fn ($t, $type) => ['name' => $t['label'], 'what' => $t['lead'], 'example' => $t['example'], 'href' => route('app.bundles.models', ['type' => $type], false)])
                ->values()->all();
        }
        if ($key === 'progressive-gifts') {
            return [['name' => 'Rewards by cart value or items', 'what' => 'Free shipping, gifts and discounts that unlock one after another in one progress bar.', 'example' => '$50 free shipping, $80 free gift, $120 15% off', 'href' => route('app.gifts.models', [], false)]];
        }

        return collect($feature['types'])->filter(fn ($type) => Registry::has($type) && empty(Registry::type($type)['retired']))
            ->map(fn ($type) => ['name' => Registry::type($type)['singular'] ?? Registry::type($type)['label'], 'what' => Registry::type($type)['description'], 'example' => null,
                'href' => route('app.cro.experiences.create', ['type' => $type], false)])
            ->values()->all();
    }
}
