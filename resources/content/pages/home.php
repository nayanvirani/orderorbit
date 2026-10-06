<?php

/*
| Home page. Text may use *highlight* (accent colour), **bold** and [link text](/path).
| Links: a path, a full URL, or {install} / {signin}. {from_price} is the cheapest paid plan,
| {max_price} the most expensive, from Plans & features.
*/

return [
    'seo_title' => 'OrderOrbit Space | Shopify CRO, Checkout & Upsell App',
    'seo_description' => 'Bundles, progressive gifts, upsells, checkout blocks, automation, analytics and A/B testing for Shopify — in one app.',
    'hero' => [
        'eyebrow' => 'A Shopify app for higher order value',
        'title' => 'Sell more to every shopper, *without fighting your theme.*',
        'lead' => 'Bundles, progressive gifts, upsells, countdowns, pre-orders, sales pops and trust blocks that look like part of your store, apply their savings at checkout, and show you exactly what they earn.',
        'cta_primary' => ['label' => 'Install on Shopify', 'href' => '{install}'],
        'cta_secondary' => ['label' => 'See how it works', 'href' => '/how-it-works'],
        'checks' => ['Free plan, no card', 'Billed through Shopify', 'No theme code edits'],
        'mockup' => [
            'url' => 'glowlab.com/products/vitamin-c-serum',
            'product' => 'Vitamin C Serum',
            'price' => '$29.00',
            'bundle_label' => 'Bundle & save',
            'tiers' => [
                ['title' => '1 Product', 'note' => 'Standard price', 'badge' => '', 'price' => '$29.00'],
                ['title' => '2 Products', 'note' => 'You save $5.80', 'badge' => '−10%', 'price' => '$52.20'],
                ['title' => '3 Products', 'note' => 'You save $17.40', 'badge' => '−20%', 'price' => '$69.60'],
            ],
            'popular' => 'Most popular',
            'button' => 'Add to cart · $52.20',
            'gift_text' => 'Add $15.00 more to unlock a free gift',
            'gift_left' => 'Free shipping ✓',
            'gift_right' => 'Free gift',
            'timer_label' => 'Summer sale ends in',
        ],
    ],
    'surfaces' => [
        'title' => 'Shows up everywhere shoppers buy:',
        'items' => ['Product pages', 'Cart & cart drawer', 'Checkout', 'Thank You & Order Status', 'Customer accounts', 'Post-purchase'],
    ],
    'stack' => [
        'eyebrow' => 'One app, every job',
        'title' => 'Replace 5–10 apps with one.',
        'text' => 'Most stores add a separate app for every job. Each one brings its own bill, its own script and its own look. OrderOrbit Space does all of these jobs in one place.',
        'link' => ['label' => 'See every feature →', 'href' => '/features'],
        'before' => 'Today · 10 separate apps',
        'apps' => [
            ['Bundle app', '$10–40'], ['Free-gift app', '$10–30'], ['Shipping bar', '$5–15'], ['Upsell app', '$10–40'], ['Countdown timer', '$5–15'],
            ['Sticky add to cart', '$5–15'], ['Trust badges', '$5–20'], ['Analytics', '$20–50'], ['Pre-orders', '$10–30'], ['Sales pop', '$5–20'],
        ],
        'after' => 'After · 1 app',
        'price' => '${from_price}',
        'price_note' => '/ month on our first paid plan',
        'stats' => [['Apps', '10 → 1'], ['Scripts', '10 → 1'], ['Monthly', '$0–{max_price}']],
        'fine' => 'Separate-app prices are typical monthly ranges for single-purpose apps on the Shopify App Store; your own bills may differ.',
    ],
    'features' => [
        'eyebrow' => 'Features',
        'title' => 'Everything that raises order value.',
        'text' => 'Each feature has ready-made templates, shares your store\'s design and applies its savings at checkout.',
        'more_title' => 'And everything after the Buy button',
        'more_text' => 'Checkout & Thank You blocks, customer accounts, analytics, A/B testing, personalization and automation.',
        'more_link' => ['label' => 'Browse all features →', 'href' => '/features'],
    ],
    'day_one' => [
        'eyebrow' => 'Day one',
        'title' => 'What changes on day one.',
        'cards' => [
            ['One bill instead of ten', 'One subscription on your Shopify invoice, and one place to manage every offer.'],
            ['A lighter, faster store', 'One small shared runtime loads, and each feature only on pages where it appears.'],
            ['No more app conflicts', 'Features are built to work together and never edit theme code.'],
            ['One look everywhere', 'Every block shares your fonts and colours, so it feels like one brand.'],
            ['One set of numbers', 'Each order line is credited to the offer that added it — no double counting.'],
            ['Less to maintain', 'One app to update, one support team, one switch to turn things off.'],
        ],
    ],
    'analytics' => [
        'eyebrow' => 'Analytics',
        'title' => 'See exactly what each offer earns.',
        'text' => 'A Shopify pixel records views, adds to cart and orders, and credits each order line to the offer that added it. Keep what works and change what doesn\'t.',
        'link' => ['label' => 'Explore analytics →', 'href' => '/features/analytics'],
        'panel_title' => 'Revenue from offers',
        'panel_note' => 'Last 30 days · sample data',
        'stats' => [['Offer revenue', '$4,280', '▲ 18%'], ['Orders with an offer', '126', '▲ 9%'], ['Average order', '$68.40', '▲ 6%']],
    ],
    'how' => [
        'eyebrow' => 'How it works',
        'title' => 'From install to your first sale in minutes.',
        'steps' => [
            ['Install', 'Approve the permissions and choose a plan. Billing runs through your Shopify invoice.'],
            ['Pick a template', 'Choose a feature and a ready-made layout to start from.'],
            ['Make it yours', 'Set products and offers, then colours and text, with a live preview.'],
            ['Publish & measure', 'It goes live on your store, and the app shows what it earns.'],
        ],
    ],
    'pricing' => [
        'eyebrow' => 'Pricing',
        'title' => 'Start free, upgrade as you grow.',
        'link' => ['label' => 'Compare plans →', 'href' => '/pricing'],
        'per_month' => '/mo',
        'free' => '$0',
    ],
    'faq' => [
        'eyebrow' => 'Questions',
        'title' => 'Good to know.',
        'text' => 'More answers in the [Help Center](/help), or [ask us](/contact).',
        'faqs' => [
            ['What is OrderOrbit Space?', 'A Shopify app that helps you raise order value and conversion with bundles, progressive gifts (free gifts, free shipping and discounts), cart upsells, countdowns, pre-orders, sales pop, sticky add-to-cart and trust blocks — with built-in analytics that show what each offer earns.'],
            ['Can it replace the apps I already use?', 'For most stores, yes. If you run separate apps for bundles, free gifts, a shipping bar, upsells, timers, pre-orders, sales pops, a sticky add-to-cart or trust badges, you can recreate those offers in OrderOrbit Space, check them on your store, then uninstall the old apps.'],
            ['Will it slow down or break my theme?', 'No. Offers load only on the pages where they appear, and each one is a small script. There are no theme code edits, and you can remove any block in one click.'],
            ['Do bundles work with my inventory?', 'Yes. A bundle shows as one line in the cart at the bundle price, but your orders keep each product, so Shopify deducts stock from every item as usual.'],
            ['Do shoppers need a discount code?', 'No. Bundle prices, gifts, free shipping and upsell incentives are applied automatically at checkout.'],
            ['Is there a free plan?', 'Yes. The Free plan is free forever, not a trial. Paid plans start at ${from_price} a month, billed through your Shopify invoice.'],
        ],
    ],
    'cta' => [
        'title' => 'Ready to raise your order value?',
        'text' => 'It takes a few minutes to publish your first offer.',
        'primary' => ['label' => 'Install on Shopify', 'href' => '{install}'],
        'secondary' => ['label' => 'Talk to us', 'href' => '/contact'],
    ],
];
