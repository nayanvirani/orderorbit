<?php

// Plan defaults from "MVP Pricing & Plan Accessibility (2026)". Only defaults: the live plans,
// features and limits are in the plans table and edited in the super admin.
$free = ['bundles', 'progressive_gifts', 'countdown', 'sticky_atc', 'trust', 'preorder', 'sales_pop', 'offer_analytics', 'automation'];
$starter = [...$free, 'quantity_breaks', 'product_upsells', 'cart_upsells'];
$growth = [...$starter, 'checkout', 'thank_you', 'post_purchase', 'advanced_analytics', 'funnels_attribution', 'customer_journeys',
    'automation_branching', 'automation_webhooks', 'ab_testing', 'ab_traffic_guardrails', 'personalization', 'priority_support'];
$scale = [...$growth, 'customer_accounts', 'ab_testing_advanced', 'personalization_advanced'];

// Usage limits per plan (null = unlimited, 0 = none). Every limit in App\Services\Usage::METERS.
$limits = fn (array $caps) => $caps + array_fill_keys([
    'active_experiences', 'bundles', 'free_gifts', 'shipping_bars', 'product_upsells', 'cart_upsells', 'countdowns', 'sticky_atc', 'trust',
    'preorders', 'sales_pop', 'checkout_blocks', 'thank_you_blocks', 'post_purchase', 'account_blocks', 'running_tests',
    'personalization_rules', 'segments', 'workflows', 'automation_executions',
], null);

// Free and Starter: what isn't in the plan is 0, so the limits read the same as the features.
$none = ['product_upsells' => 0, 'cart_upsells' => 0, 'checkout_blocks' => 0, 'thank_you_blocks' => 0, 'post_purchase' => 0, 'account_blocks' => 0, 'running_tests' => 0, 'personalization_rules' => 0, 'segments' => 0];

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

        // Merchants see a warning when any usage limit reaches this share.
        'warn_at' => 0.8,

        'plans' => [
            'free' => [
                'name' => 'Free', 'shopify_name' => env('SHOPIFY_PLAN_NAME_FREE', 'Free'), 'price' => 0,
                'description' => 'New and testing stores', 'support' => 'Community / standard',
                'includes' => $free,
                'features' => ['1 active experience', '1 bundle, 1 free-gift campaign and 1 shipping bar', 'Countdown, sticky add to cart and trust badges', '1 automation workflow, 30 runs a month', 'Basic analytics'],
                'limits' => $limits(['active_experiences' => 4, 'bundles' => 1, 'free_gifts' => 1, 'shipping_bars' => 1, 'countdowns' => 1, 'sticky_atc' => 1, 'trust' => 1, 'preorders' => 1, 'sales_pop' => 1, 'workflows' => 1, 'automation_executions' => 30] + $none),
            ],
            'starter' => [
                'name' => 'Starter', 'shopify_name' => env('SHOPIFY_PLAN_NAME_STARTER', 'Starter'), 'price' => 14.99,
                'description' => 'Growing stores', 'support' => 'Standard',
                'includes' => $starter,
                'features' => ['5 active experiences', '2 bundles, 2 free-gift campaigns and 2 shipping bars', 'Quantity breaks, product and cart upsells', '5 workflows, 200 runs a month', 'Basic analytics'],
                'limits' => $limits(['active_experiences' => 11, 'bundles' => 2, 'free_gifts' => 2, 'shipping_bars' => 2, 'product_upsells' => 5, 'cart_upsells' => 5, 'countdowns' => 5, 'sticky_atc' => 5, 'trust' => 5, 'preorders' => 5, 'sales_pop' => 5, 'workflows' => 5, 'automation_executions' => 200] + $none),
            ],
            'growth' => [
                'name' => 'Growth', 'shopify_name' => env('SHOPIFY_PLAN_NAME_GROWTH', 'Growth'), 'price' => 39.99,
                'description' => 'Serious growth stores', 'support' => 'Priority', 'badge' => 'Most Popular',
                'includes' => $growth,
                'features' => ['Unlimited experiences, bundles, gifts and upsells', 'Unlimited workflows, 3,000 runs a month, branching and webhooks', 'Funnels, attribution and customer journeys', 'Checkout, Thank You and Order Status blocks', 'A/B and A/B/C testing with guardrails', 'Basic personalization rules'],
                'limits' => $limits(['account_blocks' => 0, 'automation_executions' => 3000]),
            ],
            'scale' => [
                'name' => 'Scale', 'shopify_name' => env('SHOPIFY_PLAN_NAME_SCALE', 'Scale'), 'price' => 79.99,
                'description' => 'Advanced and high-growth stores', 'support' => 'Priority / enhanced',
                'includes' => $scale,
                'features' => ['Everything in Growth', '10,000 automation runs a month', 'Advanced personalization (VIP, device, cart value, lifecycle)', 'Customer account blocks', 'Advanced experimentation', 'Priority / enhanced support'],
                'limits' => $limits(['automation_executions' => 10000]),
            ],
        ],
    ],

];
