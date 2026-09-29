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

    'scopes' => env('SHOPIFY_SCOPES', 'read_products,read_themes,write_discounts,write_pixels,read_customer_events'),

    /*
    |--------------------------------------------------------------------------
    | Billing (section 47)
    |--------------------------------------------------------------------------
    |
    | Charges are created as test charges unless SHOPIFY_BILLING_TEST is false.
    | Limits are per store; null means unlimited.
    |
    */

    'billing' => [
        'test' => env('SHOPIFY_BILLING_TEST', true),
        'trial_days' => (int) env('SHOPIFY_TRIAL_DAYS', 7),
        'currency' => 'USD',
        'plans' => [
            'starter' => [
                'name' => 'Starter',
                'price' => 9.99,
                'features' => ['5 active experiences', '1 bundle', '1 free-gift campaign', '1 shipping bar', '5 workflows', '1,000 automation executions', 'Basic analytics'],
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
                'price' => 29.99,
                'features' => ['Unlimited CRO experiences', 'Bundles, free gifts & shipping bars', 'Advanced automation', '10,000 automation executions', 'Advanced analytics', 'Checkout & Thank You blocks', 'A/B testing'],
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
                'price' => 59.99,
                'features' => ['Everything in Growth', 'Advanced personalization', 'Experiments', 'Customer Account blocks', 'Higher limits', 'Priority support'],
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
