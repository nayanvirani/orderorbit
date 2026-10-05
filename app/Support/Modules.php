<?php

namespace App\Support;

use App\Experiences\BundleSchema;
use App\Experiences\Registry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Every feature a plan (or a single store, through its entitlements) can switch on or off.
 * A plan "includes" a list of these keys; App\Models\Store::planIncludes() checks one.
 * A feature with a parent only works when the parent is included too.
 * Labels, descriptions and pricing-page visibility can be changed in the super admin.
 */
class Modules
{
    /** key => [label, group, help, parent] */
    public const ALL = [
        'bundles' => ['Bundles', 'Storefront', 'Fixed bundles, mix & match, BOGO and gift bundles.', null],
        'quantity_breaks' => ['Quantity breaks', 'Storefront', 'Buy more, pay less (and quantity + gift) bundles.', 'bundles'],
        'progressive_gifts' => ['Free-gift campaigns & shipping bars', 'Storefront', 'Free gifts, free shipping and discounts that unlock with the cart.', null],
        'product_upsells' => ['Product upsells', 'Storefront', 'Add-on offers on product pages.', null],
        'cart_upsells' => ['Cart upsells & cross-sell', 'Storefront', 'Add-on offers in the cart.', null],
        'countdown' => ['Countdown timer', 'Storefront', 'Campaign countdowns.', null],
        'sticky_atc' => ['Sticky add to cart', 'Storefront', 'A buy bar that follows the page.', null],
        'trust' => ['Trust & social proof', 'Storefront', 'Reviews, badges and guarantees.', null],
        'preorder' => ['Pre-order', 'Storefront', 'Sell before stock arrives.', null],
        'sales_pop' => ['Sales pop', 'Storefront', 'Recent-purchase notifications.', null],

        'checkout' => ['Checkout blocks', 'Checkout', 'Blocks inside checkout, where Shopify supports them.', null],
        'thank_you' => ['Thank You & Order Status', 'Checkout', 'Blocks on the Thank You and Order Status pages.', null],
        'post_purchase' => ['Post-purchase offers', 'Checkout', 'A one-click offer after payment.', null],
        'customer_accounts' => ['Customer account blocks', 'Checkout', 'Reorder, reviews, rewards, products and support in customer accounts.', null],

        'offer_analytics' => ['Basic analytics', 'Analytics', 'Store totals and views, adds, orders and revenue per offer.', null],
        'advanced_analytics' => ['Advanced analytics', 'Analytics', 'Event Explorer and advanced reports.', null],
        'funnels_attribution' => ['Funnels & revenue attribution', 'Analytics', 'Funnels and revenue by source and offer.', null],
        'customer_journeys' => ['Customer journeys', 'Analytics', 'Every visit and order of a customer.', null],

        'automation' => ['Automation workflows', 'Automation', 'Lifecycle workflows: tags, codes, emails and tasks.', null],
        'automation_branching' => ['Advanced workflow branching', 'Automation', 'If / else conditions in workflows.', 'automation'],
        'automation_webhooks' => ['Webhooks & advanced actions', 'Automation', 'Webhook steps that call your own systems.', 'automation'],

        'ab_testing' => ['A/B testing', 'Testing', 'A/B and A/B/C tests.', null],
        'ab_traffic_guardrails' => ['Traffic allocation & guardrails', 'Testing', 'Uneven splits, holdouts and guardrail metrics.', 'ab_testing'],
        'ab_testing_advanced' => ['Advanced experimentation', 'Testing', 'Tests for chosen audiences (segments).', 'ab_testing'],

        'personalization' => ['Personalization (basic rules)', 'Personalization', 'Segments by country, sign-in, customer tags, traffic source and offer activity.', null],
        'personalization_advanced' => ['Advanced personalization', 'Personalization', 'VIP, returning-customer, device, cart-value, product and lifecycle targeting.', 'personalization'],

        'priority_support' => ['Priority support', 'Service', 'Tickets answered first.', null],
    ];

    /** Bundle types that need Quantity breaks. */
    public const QUANTITY_BUNDLES = ['quantity-breaks', 'quantity-gifts'];

    private const OVERRIDES_KEY = 'feature-catalog:v1';

    /** The catalog with the super admin's label, help and pricing-page changes. key => [label, group, help, parent, public] */
    public static function all(): array
    {
        $overrides = self::overrides();

        return collect(self::ALL)->map(fn ($m, $key) => [
            $overrides[$key]['label'] ?? $m[0], $m[1], $overrides[$key]['help'] ?? $m[2], $m[3], $overrides[$key]['public'] ?? true,
        ])->all();
    }

    public static function label(string $key): string
    {
        return self::all()[$key][0] ?? $key;
    }

    /** @return array<string, array<string, array>> group => features */
    public static function grouped(): array
    {
        return collect(self::all())->groupBy(fn ($m) => $m[1], true)->map->all()->all();
    }

    public static function overrides(): array
    {
        try {
            return Cache::rememberForever(self::OVERRIDES_KEY, fn () => (array) json_decode((string) DB::table('platform_settings')->where('key', 'feature_catalog')->value('value'), true));
        } catch (Throwable) {
            return [];
        }
    }

    public static function saveOverrides(array $overrides): void
    {
        DB::table('platform_settings')->updateOrInsert(['key' => 'feature_catalog'], ['value' => json_encode($overrides), 'updated_at' => now(), 'created_at' => now()]);
        Cache::forget(self::OVERRIDES_KEY);
    }

    /** The feature an experience type belongs to (null: not gated). */
    public static function forType(string $type): ?string
    {
        return match (true) {
            in_array($type, ['bundles', 'bogo'], true) => 'bundles',
            $type === 'quantity-breaks' => 'quantity_breaks',
            in_array($type, ['progressive-gifts', 'free-gifts', 'shipping-bar'], true) => 'progressive_gifts',
            $type === 'product-upsells' => 'product_upsells',
            $type === 'post-purchase' => 'post_purchase',
            default => match (Registry::has($type) ? Registry::type($type)['surface'] : null) {
                'checkout' => 'checkout',
                'thank-you' => 'thank_you',
                'account' => 'customer_accounts',
                'post-purchase' => 'post_purchase',
                default => ($feature = Registry::featureFor($type)) && isset(self::ALL[$key = str_replace('-', '_', $feature)]) ? $key : null,
            },
        };
    }

    /**
     * Every feature an experience needs with this configuration: its type's feature, plus
     * Quantity breaks for quantity-break bundles.
     *
     * @return list<string>
     */
    public static function forExperience(string $type, ?array $config = null): array
    {
        $needs = array_filter([self::forType($type)]);
        if ($type === 'bundles' && $config !== null && in_array(BundleSchema::normalize($config)[0]['bundle_type'] ?? null, self::QUANTITY_BUNDLES, true)) {
            $needs[] = 'quantity_breaks';
        }

        return array_values(array_unique($needs));
    }

    /** A feature's key plus its parents (a child only works with its parent). */
    public static function chain(string $key): array
    {
        $chain = [$key];
        while (($parent = self::ALL[$key][3] ?? null) !== null) {
            $chain[] = $key = $parent;
        }

        return $chain;
    }
}
