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

        // Next MVP modules (G29): added to every plan as section 8 sets out; today's keys are unchanged.
        'product_documents' => ['Product documents in emails', 'Automation', 'Certificates and PDFs sent with workflow emails by product.', 'automation'],
        'translations' => ['Widget translations', 'Storefront', 'Widget text per store language.', null],
        'profit_snapshot' => ['Profit snapshot', 'Profit', 'The last 30 days of sales and profit.', null],
        'profit' => ['Profit', 'Profit', 'Costs, ad spend, dashboard and breakdowns.', null],
        'profit_roas' => ['ROAS and POAS', 'Profit', 'Return on ad spend and profit on ad spend.', 'profit'],
        'profit_goals' => ['Profit goals', 'Profit', 'Monthly goals and pacing.', 'profit'],
        'protect' => ['Protect', 'Protect', 'Health Score, Action Center, low stock and three alerts.', null],
        'protect_alerts' => ['All alerts and days of stock', 'Protect', 'Every money-leak alert and days of stock.', 'protect'],
        'product_data' => ['Product data quality', 'Protect', 'Missing images, costs, SKUs and more.', 'protect'],
        'url_monitor' => ['URL monitor', 'Protect', 'Key pages checked around the clock.', 'protect'],
        'sales_insights' => ['Sales insights and reports', 'Insights', 'Sales page, Monthly Business Report and all store questions.', null],
        'customer_value' => ['Customer value insights', 'Insights', 'Repeat rate, time to second order and best first products.', null],
        'morning_brief' => ['AI Morning Brief', 'Insights', 'A daily five-line brief.', null],
        'ai_offer_ideas' => ['AI offer ideas', 'AI', 'Offer text and bundles from products bought together.', null],
        'reorder_timing' => ['Predictive reorder timing', 'AI', 'Reorder reminders at each product\'s usual gap.', 'automation'],
        'review_apps' => ['Judge.me, Yotpo and Loox', 'Integrations', 'Real star ratings and reviews in offers.', null],
        'klaviyo_ga4' => ['Klaviyo and GA4', 'Integrations', 'Segments and events in Klaviyo and Google Analytics.', null],
        'first_in_line_support' => ['First-in-line support', 'Service', 'Answered before every other plan.', 'priority_support'],
    ];

    /**
     * Next MVP modules with a release flag. Each one counts as "Coming soon" (never promised as
     * available on the pricing page, the custom plan form or upgrade prompts) until its flag is on
     * for every store; see coming().
     */
    public const COMING = ['translations', 'profit_snapshot', 'profit', 'profit_roas', 'profit_goals', 'protect', 'protect_alerts', 'product_data', 'url_monitor',
        'sales_insights', 'customer_value', 'morning_brief', 'ai_offer_ideas', 'reorder_timing', 'review_apps', 'klaviyo_ga4', 'first_in_line_support'];

    /** The release flag behind each module in COMING. */
    public const COMING_FLAGS = [
        'translations' => 'translations', 'profit_snapshot' => 'profit', 'profit' => 'profit', 'profit_roas' => 'profit', 'profit_goals' => 'profit_goals',
        'protect' => 'protect', 'protect_alerts' => 'protect', 'product_data' => 'protect', 'url_monitor' => 'url_monitor',
        'sales_insights' => 'sales_insights', 'customer_value' => 'customer_value', 'morning_brief' => 'morning_brief', 'ai_offer_ideas' => 'ai_offer_ideas',
        'reorder_timing' => 'reorder_timing', 'review_apps' => 'review_apps', 'klaviyo_ga4' => 'integrations', 'first_in_line_support' => 'premium_plan',
    ];

    /** Modules not yet released to every store: COMING minus those whose flag is on for all. */
    public static function coming(): array
    {
        return array_values(array_filter(self::COMING, fn ($key) => ! Features::enabled(self::COMING_FLAGS[$key] ?? $key)));
    }

    /** The next MVP's module keys per plan (G29, section 8), added to each plan's own list. */
    public const NEXT_MVP = [
        'free' => ['profit_snapshot', 'protect'],
        'starter' => ['profit_snapshot', 'protect', 'profit', 'protect_alerts', 'sales_insights', 'review_apps'],
        'growth' => ['profit_snapshot', 'protect', 'profit', 'protect_alerts', 'sales_insights', 'review_apps', 'profit_roas', 'product_data', 'url_monitor', 'translations', 'klaviyo_ga4', 'product_documents'],
        'scale' => ['profit_snapshot', 'protect', 'profit', 'protect_alerts', 'sales_insights', 'review_apps', 'profit_roas', 'product_data', 'url_monitor', 'translations', 'klaviyo_ga4', 'product_documents',
            'profit_goals', 'customer_value'],
        'premium' => ['profit_snapshot', 'protect', 'profit', 'protect_alerts', 'sales_insights', 'review_apps', 'profit_roas', 'product_data', 'url_monitor', 'translations', 'klaviyo_ga4', 'product_documents',
            'profit_goals', 'customer_value', 'morning_brief', 'ai_offer_ideas', 'reorder_timing', 'first_in_line_support'],
    ];

    /** The next MVP's limits per plan (null = unlimited): URL monitor pages, team members who see profit, days of history, store questions. */
    public const NEXT_MVP_LIMITS = [
        'free' => ['url_monitors' => 0, 'profit_viewers' => 1, 'history_days' => 30, 'store_questions' => 10],
        'starter' => ['url_monitors' => 0, 'profit_viewers' => 2, 'history_days' => 365, 'store_questions' => null],
        'growth' => ['url_monitors' => 20, 'profit_viewers' => 3, 'history_days' => 730, 'store_questions' => null],
        'scale' => ['url_monitors' => 50, 'profit_viewers' => 10, 'history_days' => 1095, 'store_questions' => null],
        'premium' => ['url_monitors' => 100, 'profit_viewers' => null, 'history_days' => 1095, 'store_questions' => null],
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
