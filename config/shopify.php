<?php

// Live-offer limits per plan (null = unlimited). Only Free caps the revenue features at one each.
$limits = fn (array $caps, int $automationRuns) => $caps + [
    'active_experiences' => null,
    'bundles' => null,
    'free_gifts' => null,
    'cart_upsells' => null,
    'preorders' => null,
    'shipping_bars' => null,
    'workflows' => null,
    'automation_executions' => $automationRuns,
];

return [

    /*
    |--------------------------------------------------------------------------
    | Shopify app credentials
    |--------------------------------------------------------------------------
    |
    | Set in the hosting environment (Railway variables). Never commit them.
    |
    */

    'api_key' => env('SHOPIFY_API_KEY'),

    'api_secret' => env('SHOPIFY_API_SECRET'),

    'api_version' => env('SHOPIFY_API_VERSION', '2026-07'),

    // Public "Install on Shopify" CTA; the App Store listing once it is live.
    'install_url' => env('SHOPIFY_INSTALL_URL', '#install'),

    // Header "Sign In": merchants authenticate through Shopify, not a separate password.
    'sign_in_url' => env('SHOPIFY_SIGN_IN_URL', 'https://admin.shopify.com'),

    'scopes' => env('SHOPIFY_SCOPES', 'read_products,read_themes,write_discounts,write_cart_transforms,write_pixels,read_customer_events,write_orders,write_customers,write_files'),

    /*
    |--------------------------------------------------------------------------
    | Billing (section 47) — Shopify Managed Pricing
    |--------------------------------------------------------------------------
    |
    | Plans, prices and trials are configured in the Partner Dashboard (App →
    | Distribution → Manage listing → Pricing). Merchants pick a plan on
    | Shopify's own hosted page; the app never creates or cancels charges. It
    | learns the plan from the app_subscriptions/update webhook and a live
    | sync, matching Shopify's plan display name to "shopify_name" below.
    |
    | app_handle is the app's handle in admin URLs
    | (admin.shopify.com/store/{shop}/apps/{app_handle}), used for the
    | pricing page link .../charges/{app_handle}/pricing_plans.
    |
    | test_shops: our own dev stores that get plan access without a
    | subscription (comma-separated *.myshopify.com). Managed Pricing can get
    | stuck on dev stores; this must never block testing. Real merchants
    | always need a real subscription.
    |
    */

    // Fallback only: the handle is read from Shopify on every billing sync.
    'app_handle' => env('SHOPIFY_APP_HANDLE', 'orderorbit'),

    'test_shops' => array_values(array_filter(array_map('trim', explode(',', (string) env('ORDERORBIT_TEST_SHOPS', ''))))),

    'test_shop_plan' => env('ORDERORBIT_TEST_SHOP_PLAN', 'scale'),

    'billing' => [
        'currency' => 'USD',

        // Every plan has every feature. Plans differ by the store's total sales in its current
        // 30-day cycle (USD, all orders except test and cancelled ones; cycles run from the first
        // install). "sales_limit" null = unlimited. Past the limit the merchant has "grace_days"
        // to upgrade before every feature stops; a stopped store stays stopped until it upgrades.
        'grace_days' => 3,

        // Test orders (every order on a development store) never count toward the limit, except
        // for the shops listed here: our own stores, so the limit flow can be tried end to end.
        'count_test_orders_for' => array_values(array_filter(array_map('trim', explode(',', (string) env('ORDERORBIT_COUNT_TEST_ORDERS_FOR', ''))))),
        'warn_at' => 0.8,

        // "includes" lists what a plan unlocks beyond the storefront features every plan has:
        // offer_analytics (revenue per offer), advanced_analytics (events, funnels, attribution,
        // journeys) and, as they ship, checkout, customer_accounts,
        // ab_testing, automation and personalization.
        'plans' => [
            'free' => [
                'name' => 'Free',
                'shopify_name' => env('SHOPIFY_PLAN_NAME_FREE', 'Free'),
                'price' => 0,
                'sales_limit' => 1000,
                'includes' => [],
                'features' => ['Up to $1,000 in monthly store sales', 'Countdown, sticky add to cart, trust badges and sales pop', '1 bundle, 1 gift campaign, 1 cart upsell and 1 pre-order', 'Every template', 'Store analytics'],
                'limits' => $limits(['bundles' => 1, 'free_gifts' => 1, 'cart_upsells' => 1, 'preorders' => 1], 1000),
            ],
            'starter' => [
                'name' => 'Starter',
                'shopify_name' => env('SHOPIFY_PLAN_NAME_STARTER', 'Starter'),
                'price' => 14.99,
                'sales_limit' => 8000,
                'includes' => ['offer_analytics'],
                'features' => ['Up to $8,000 in monthly store sales', 'Everything in Free', 'Unlimited bundles, gifts, upsells and pre-orders', 'Analytics with revenue per offer'],
                'limits' => $limits([], 10000),
            ],
            'growth' => [
                'name' => 'Growth',
                'shopify_name' => env('SHOPIFY_PLAN_NAME_GROWTH', 'Growth'),
                'price' => 29.99,
                'sales_limit' => 20000,
                'includes' => ['offer_analytics', 'advanced_analytics', 'checkout', 'customer_accounts', 'ab_testing'],
                'features' => ['Up to $20,000 in monthly store sales', 'Everything in Starter', 'Checkout, Thank You and Order Status blocks', 'Funnels, attribution, event explorer and customer journeys', 'A/B testing and customer account blocks when released'],
                'limits' => $limits([], 10000),
            ],
            'scale' => [
                'name' => 'Scale',
                'shopify_name' => env('SHOPIFY_PLAN_NAME_SCALE', 'Scale'),
                'price' => 59.99,
                'sales_limit' => null,
                'includes' => ['offer_analytics', 'advanced_analytics', 'checkout', 'customer_accounts', 'ab_testing', 'automation', 'personalization', 'priority_support'],
                'features' => ['Unlimited store sales', 'Everything in Growth', 'Lifecycle automation workflows', 'Personalization when released', 'Priority support'],
                'limits' => $limits([], 50000),
            ],
        ],

        // Approximate USD value of one unit of each currency, used when live rates can't be
        // fetched. Currencies not listed count 1:1.
        'usd_rates' => [
            'USD' => 1, 'EUR' => 1.08, 'GBP' => 1.27, 'CAD' => 0.73, 'AUD' => 0.66, 'NZD' => 0.60, 'INR' => 0.012,
            'JPY' => 0.0067, 'CHF' => 1.13, 'SEK' => 0.095, 'NOK' => 0.093, 'DKK' => 0.145, 'SGD' => 0.75,
            'HKD' => 0.128, 'AED' => 0.272, 'SAR' => 0.267, 'ZAR' => 0.055, 'BRL' => 0.18, 'MXN' => 0.055,
            'PLN' => 0.25, 'CZK' => 0.043, 'ILS' => 0.27, 'MYR' => 0.22, 'THB' => 0.028, 'PHP' => 0.017,
            'IDR' => 0.000062, 'KRW' => 0.00073, 'CNY' => 0.14, 'TRY' => 0.029, 'HUF' => 0.0028, 'RON' => 0.22,
        ],
    ],

];
