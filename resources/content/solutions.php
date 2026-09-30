<?php

/*
| Solutions pages — Combined MVP Scope, section 7 and Part E3.
| "loop" marks which core-loop stages the recommended setup uses.
*/

return [

    'dtc-brands' => [
        'name' => 'DTC Brands',
        'tagline' => 'One app instead of an app stack',
        'icon' => 'rocket',
        'visual' => 'bundles',
        'h1' => 'Grow conversion and order value without an app stack.',
        'hero' => 'Replace separate bundle, upsell, timer, trust and email apps with one theme-safe platform and one set of numbers.',
        'seo_description' => 'One Shopify app for bundles, upsells, incentives, checkout, automation and testing — built for DTC brands.',
        'problem' => ['Five apps, five setups, five dashboards.', 'Each one slows your pages a little and claims a little credit. Nobody can say what actually worked.'],
        'setup' => ['Bundle on hero products', 'Progressive gifts with free shipping', 'Cart upsell', 'Trust row near ATC', 'Review Request workflow', 'A/B test the bundle layout'],
        'features' => ['bundles', 'progressive-gifts', 'cart-upsells', 'trust-social-proof', 'automation', 'ab-testing'],
        'loop' => ['create', 'publish', 'measure', 'test', 'automate'],
        'example' => ['title' => 'A home-fragrance brand.', 'text' => 'Launch a "Build your candle trio" bundle, add progressive gifts with free shipping at $60, then test two bundle layouts on the product page.', 'flow' => ['Candle trio bundle', '$60 shipping bar', 'A/B: Tier Cards vs Radio', 'Keep the winner']],
        'faqs' => [
            ['Can I replace my current bundle app?', 'Yes; recreate bundles in OrderOrbit Space, place the block, then remove the old app.'],
            ['Will it slow my store?', 'Blocks load only where placed.'],
        ],
    ],

    'repeat-purchase' => [
        'name' => 'Repeat-Purchase Brands',
        'tagline' => 'Beauty, food, supplements, consumables',
        'icon' => 'repeat',
        'visual' => 'customer-accounts',
        'h1' => 'Turn first orders into routines.',
        'hero' => 'Routine bundles, reorder reminders and one-click reorder in customer accounts for beauty, food, supplements and other consumables.',
        'seo_description' => 'Routine bundles, reorder reminders and one-click reorder for Shopify beauty, food and supplement brands.',
        'problem' => ['Your margin is in the second order.', 'Customers run out, forget where they bought, and buy elsewhere.'],
        'setup' => ['Mix & match routine bundle', 'Quantity break bundle', 'Thank You page reorder block', 'Reorder Reminder workflow timed per product', 'Customer Account Reorder'],
        'features' => ['bundles', 'progressive-gifts', 'checkout', 'customer-accounts', 'automation', 'personalization'],
        'loop' => ['create', 'publish', 'personalize', 'automate'],
        'example' => ['title' => 'A supplement brand.', 'text' => 'A 30-day supply triggers a reminder at day 24 with a one-click reorder link.', 'flow' => ['30-day supply ordered', 'Wait 24 days', 'Reorder reminder', 'One-click reorder']],
        'faqs' => [
            ['Does it work with subscriptions?', 'Bundles and quantity breaks respect selling plans where supported; OrderOrbit Space is not a subscription app.'],
        ],
    ],

    'fashion-apparel' => [
        'name' => 'Fashion & Apparel',
        'tagline' => 'Sell the outfit, not the single item',
        'icon' => 'shirt',
        'visual' => 'cart-upsells',
        'h1' => 'Build bigger baskets.',
        'hero' => 'Complete-the-look bundles, progressive gifts and trust blocks that help shoppers buy the outfit, not the single item.',
        'seo_description' => 'Complete-the-look bundles, progressive gifts and trust blocks for Shopify fashion and apparel brands.',
        'problem' => ['One item, then gone.', 'Fashion shoppers browse a lot and buy one piece, often after comparing return policies.'],
        'setup' => ['Product upsell "Complete the look"', 'Free-gift ladder at two thresholds', 'Guarantee card with returns promise', 'Countdown for real sale end dates', 'Win-back workflow'],
        'features' => ['bundles', 'progressive-gifts', 'trust-social-proof', 'countdown-timer', 'automation'],
        'loop' => ['create', 'publish', 'measure', 'automate'],
        'example' => ['title' => 'A denim brand.', 'text' => 'Jeans page recommends the matching jacket; spend $150 unlocks a free tote.', 'flow' => ['Viewing jeans', 'Complete the look: jacket', 'Cart $150', 'Free tote unlocked']],
        'faqs' => [
            ['Can recommendations depend on the product?', 'Yes, by product or collection rules.'],
        ],
    ],

    'shopify-plus' => [
        'name' => 'Shopify Plus Brands',
        'tagline' => 'Full checkout-block access',
        'icon' => 'star',
        'visual' => 'checkout',
        'h1' => 'Extend checkout with confidence.',
        'hero' => 'Checkout blocks for trust, reviews, shipping progress and offers on supported Plus targets — plus experiments and personalization across your store.',
        'seo_description' => 'Checkout blocks for trust, reviews, shipping progress and offers on Shopify Plus, with experiments and personalization.',
        'problem' => ['Checkout is your most valuable page, and your most fragile.', 'Custom code is gone; supported checkout blocks are the way forward.'],
        'setup' => ['Checkout trust row', 'Checkout shipping progress', 'Thank You cross-sell', 'Order Status tracking/support', 'Segmented upsells', 'A/B tests on checkout blocks'],
        'features' => ['checkout', 'customer-accounts', 'ab-testing', 'personalization', 'analytics'],
        'loop' => ['create', 'publish', 'measure', 'test', 'personalize'],
        'example' => ['title' => 'A premium outdoor brand.', 'text' => 'Test a guarantee banner vs a review slider in checkout, with cart abandonment as a guardrail.', 'flow' => ['A: Guarantee banner', 'B: Review slider', 'Guardrail: abandonment', 'Ship the winner']],
        'faqs' => [
            ['Does OrderOrbit Space replace checkout?', 'No. It adds blocks to checkout in the ways Shopify supports.'],
        ],
    ],

];
