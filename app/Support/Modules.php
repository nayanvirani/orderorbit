<?php

namespace App\Support;

use App\Experiences\Registry;

/**
 * Everything a plan (or a single store, through its entitlements) can switch on or off.
 */
class Modules
{
    public const ALL = [
        'bundles' => ['Bundles', 'Storefront', 'Quantity breaks, mix & match, fixed bundles and gift bundles.'],
        'progressive_gifts' => ['Progressive gifts', 'Storefront', 'Free gifts, free shipping and discounts that unlock with the cart.'],
        'cart_upsells' => ['Cart upsells', 'Storefront', 'Add-on offers in the cart.'],
        'countdown' => ['Countdown timer', 'Storefront', 'Campaign countdowns.'],
        'sticky_atc' => ['Sticky add to cart', 'Storefront', 'A buy bar that follows the page.'],
        'preorder' => ['Pre-order', 'Storefront', 'Sell before stock arrives.'],
        'sales_pop' => ['Sales pop', 'Storefront', 'Recent-purchase notifications.'],
        'trust' => ['Trust & social proof', 'Storefront', 'Reviews, badges and guarantees.'],
        'checkout' => ['Checkout & Thank You', 'Checkout', 'Checkout, Thank You, Order Status and post-purchase blocks.'],
        'customer_accounts' => ['Customer accounts', 'Checkout', 'Blocks in Shopify customer accounts.'],
        'offer_analytics' => ['Revenue per offer', 'Insights', 'Views, adds, orders and revenue for each offer.'],
        'advanced_analytics' => ['Advanced analytics', 'Insights', 'Event Explorer, funnels, attribution and customer journeys.'],
        'ab_testing' => ['A/B testing', 'Growth', 'A/B and A/B/C tests.'],
        'automation' => ['Automation', 'Growth', 'Lifecycle workflows.'],
        'personalization' => ['Audiences & personalization', 'Growth', 'Segments and personalization rules.'],
        'priority_support' => ['Priority support', 'Service', 'Tickets answered first.'],
    ];

    public static function label(string $key): string
    {
        return self::ALL[$key][0] ?? $key;
    }

    /** @return array<string, array<string, array{0: string, 1: string, 2: string}>> group => modules */
    public static function grouped(): array
    {
        return collect(self::ALL)->groupBy(fn ($m) => $m[1], true)->map->all()->all();
    }

    /** The module an experience type belongs to. */
    public static function forType(string $type): ?string
    {
        $feature = Registry::featureFor($type);

        return match ($feature) {
            null => null,
            'checkout', 'thank-you', 'post-purchase' => 'checkout',
            'customer-accounts' => 'customer_accounts',
            default => str_replace('-', '_', $feature),
        };
    }
}
