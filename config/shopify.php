<?php

// Plan defaults, matching the live App Store listing (apps.shopify.com/orderorbit, 4 Nov 2026). Only defaults: the live plans,
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

    // While rotating the client secret: the old one, still accepted for incoming webhooks, session
    // tokens and post-purchase tokens until it's revoked in the Dev Dashboard. Remove after revoking.
    'api_secret_previous' => env('SHOPIFY_API_SECRET_PREVIOUS'),

    'api_version' => env('SHOPIFY_API_VERSION', '2026-07'),

    // Public "Install app" buttons: the App Store listing, set in Internal Admin → Platform settings
    // once Shopify approves the app. Until then they open the contact page.
    'install_url' => env('SHOPIFY_INSTALL_URL', '/contact'),

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
                'includes' => [...$free, ...App\Support\Modules::NEXT_MVP['free']],
                'features' => ['1 widget live at once', '1 bundle and 1 free-gift campaign', 'Countdown timer and sticky add to cart', 'Trust badges and recent sales pop-ups', '1 pre-order campaign', '1 automation workflow, 50 runs a month', 'Revenue per widget analytics', 'Every template and full design settings'],
                'limits' => $limits(['active_experiences' => 1, 'bundles' => 1, 'free_gifts' => 1, 'shipping_bars' => 1, 'countdowns' => 1, 'sticky_atc' => 1, 'trust' => 2, 'preorders' => 1, 'sales_pop' => 1, 'workflows' => 1, 'automation_executions' => 50] + $none) + App\Support\Modules::NEXT_MVP_LIMITS['free'],
            ],
            'starter' => [
                'name' => 'Starter', 'shopify_name' => env('SHOPIFY_PLAN_NAME_STARTER', 'Starter'), 'price' => 14.99,
                'description' => 'Growing stores', 'support' => 'Standard',
                'includes' => [...$starter, ...App\Support\Modules::NEXT_MVP['starter']],
                'features' => ['5 widgets live at once', '1 bundle with quantity breaks', 'Product and cart upsells', '2 free-gift campaigns and shipping bars', '1 countdown, trust badges and pre-orders', '5 workflows, 500 runs a month', 'Revenue per widget analytics', 'Everything in Free'],
                'limits' => $limits(['active_experiences' => 5, 'bundles' => 1, 'free_gifts' => 2, 'shipping_bars' => 2, 'product_upsells' => 3, 'cart_upsells' => 3, 'countdowns' => 1, 'sticky_atc' => 2, 'trust' => 1, 'preorders' => 1, 'sales_pop' => 2, 'workflows' => 5, 'automation_executions' => 500] + $none) + App\Support\Modules::NEXT_MVP_LIMITS['starter'],
            ],
            'growth' => [
                'name' => 'Growth', 'shopify_name' => env('SHOPIFY_PLAN_NAME_GROWTH', 'Growth'), 'price' => 39.99,
                'description' => 'Serious growth stores', 'support' => 'Priority', 'badge' => 'Most Popular',
                'includes' => [...$growth, ...App\Support\Modules::NEXT_MVP['growth']],
                'features' => ['15 widgets live at once', 'Checkout and Thank You page blocks', 'One-click upsell after payment', 'A/B/C tests for every offer (5 live)', 'Funnels, attribution & customer journeys', 'Personalization: 20 segments and rules', '15 workflows, 1,000 runs/mo', 'Certificates & PDFs in automation emails'],
                'limits' => $limits(['active_experiences' => 15, 'account_blocks' => 0, 'running_tests' => 5, 'personalization_rules' => 20, 'segments' => 20, 'workflows' => 15, 'automation_executions' => 1000]) + App\Support\Modules::NEXT_MVP_LIMITS['growth'],
            ],
            'scale' => [
                'name' => 'Scale', 'shopify_name' => env('SHOPIFY_PLAN_NAME_SCALE', 'Scale'), 'price' => 79.99,
                'description' => 'Advanced and high-growth stores', 'support' => 'Priority / enhanced',
                'includes' => [...$scale, ...App\Support\Modules::NEXT_MVP['scale']],
                'features' => ['Everything in Growth', 'Customer account blocks', 'Reorder, rewards and reviews in accounts', 'Unlimited A/B tests', 'Tests and offers for chosen audiences', 'VIP, device and cart-value targeting', 'Unlimited segments and personalization', '5,000 automation runs a month'],
                'limits' => $limits(['automation_executions' => 5000]) + App\Support\Modules::NEXT_MVP_LIMITS['scale'],
            ],
            // Next MVP (G29): the new top plan, shown with the premium_plan flag. Set up in the Partner
            // Dashboard's managed pricing with the same name before turning the flag on.
            'premium' => [
                'name' => 'Premium', 'shopify_name' => env('SHOPIFY_PLAN_NAME_PREMIUM', 'Premium'), 'price' => 129.99,
                'description' => 'Large stores that want everything', 'support' => 'First in line',
                'includes' => [...$scale, ...App\Support\Modules::NEXT_MVP['premium']],
                'features' => ['Everything in Scale', '10,000 automation runs a month', 'Unlimited live widgets and A/B tests', 'Customer account blocks', 'Checkout and after-payment offers', 'Unlimited segments and rules', 'Certificates and PDFs in emails', 'Priority enhanced support'],
                'limits' => $limits(['automation_executions' => 10000]) + App\Support\Modules::NEXT_MVP_LIMITS['premium'],
            ],
        ],
    ],

];
