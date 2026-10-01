<?php

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

    'scopes' => env('SHOPIFY_SCOPES', 'read_products,read_themes,write_discounts,write_cart_transforms,write_pixels,read_customer_events,read_orders'),

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
        'plans' => [
            'starter' => [
                'name' => 'Starter',
                'shopify_name' => env('SHOPIFY_PLAN_NAME_STARTER', 'Starter'),
                'price' => 9.99,
                'features' => ['5 live offers', '1 bundle', '1 progressive gifts campaign', 'Upsells, countdowns, pre-orders, sales pop, sticky add to cart, trust', 'Analytics', 'Every template'],
                'limits' => [
                    'active_experiences' => 5,
                    'bundles' => 1,
                    'free_gifts' => 1,
                    'shipping_bars' => 1,
                    'workflows' => 5,
                    'automation_executions' => 1000,
                ],
            ],
            'growth' => [
                'name' => 'Growth',
                'shopify_name' => env('SHOPIFY_PLAN_NAME_GROWTH', 'Growth'),
                'price' => 29.99,
                'features' => ['Unlimited live offers', 'Unlimited bundles & progressive gifts', 'Everything in Starter', 'Analytics with revenue per offer', 'Checkout blocks and A/B testing when released'],
                'limits' => [
                    'active_experiences' => null,
                    'bundles' => null,
                    'free_gifts' => null,
                    'shipping_bars' => null,
                    'workflows' => null,
                    'automation_executions' => 10000,
                ],
            ],
            'scale' => [
                'name' => 'Scale',
                'shopify_name' => env('SHOPIFY_PLAN_NAME_SCALE', 'Scale'),
                'price' => 59.99,
                'features' => ['Everything in Growth', 'Priority support', 'Automation and personalization when released', 'Early access to new features'],
                'limits' => [
                    'active_experiences' => null,
                    'bundles' => null,
                    'free_gifts' => null,
                    'shipping_bars' => null,
                    'workflows' => null,
                    'automation_executions' => 50000,
                ],
            ],
        ],
    ],

];
