<?php

/*
| Solutions pages. "setup" uses features that are live today; "later" lists
| planned features (shown as coming soon).
*/

return [

    'dtc-brands' => [
        'name' => 'DTC Brands',
        'tagline' => 'One app instead of an app stack',
        'icon' => 'rocket',
        'visual' => 'bundles',
        'h1' => 'Raise order value without an app stack.',
        'hero' => 'Bundles, gifts, upsells, countdowns and trust blocks in one app, with one design and one set of numbers.',
        'seo_description' => 'One Shopify app for bundles, progressive gifts, upsells, countdowns and trust — built for DTC brands.',
        'overview' => [
            'Most direct-to-consumer stores end up with a bundle app, a gift app, a timer app and a reviews widget. Each has its own settings, its own look and its own claim to the revenue — and each adds a little weight to your pages.',
            'OrderOrbit Space replaces that stack with one app. Every offer shares your store\'s design, applies its price at checkout, and reports to the same analytics, so you can see which offer actually earned the money.',
        ],
        'setup' => [
            ['Quantity-break bundle on your hero product', 'Buy 1, buy 2 (most popular), buy 3 — each with its own saving.'],
            ['Progressive gifts', 'Free shipping at your free-shipping threshold and a free gift just above your average order value.'],
            ['Cart upsell', 'One relevant add-on in the cart, with a small incentive.'],
            ['Trust row near the buy button', 'Shipping, returns and secure checkout, in your colours.'],
        ],
        'later' => ['A/B test two bundle layouts', 'Review request emails after delivery'],
        'features' => ['bundles', 'progressive-gifts', 'cart-upsells', 'trust-social-proof', 'analytics'],
        'faqs' => [
            ['Can I replace my current bundle app?', 'Yes. Recreate your bundles in OrderOrbit Space, check them on your store, then remove the old app.'],
            ['Will it slow my store?', 'Each offer loads only on the pages where it appears, and there are no theme code edits.'],
        ],
    ],

    'repeat-purchase' => [
        'name' => 'Repeat-Purchase Brands',
        'tagline' => 'Beauty, food, supplements, consumables',
        'icon' => 'repeat',
        'visual' => 'bundles',
        'h1' => 'Sell the routine, not just the product.',
        'hero' => 'Routine bundles, multi-packs and gifts that reward bigger orders for beauty, food, supplements and other consumables.',
        'seo_description' => 'Routine bundles, multi-packs and gift rewards for Shopify beauty, food and supplement brands.',
        'overview' => [
            'When customers buy a product they use up, the best time to grow the order is the first purchase. A routine bundle or a three-pack turns one bottle into a month\'s supply, and a gift at the right threshold makes the bigger basket feel like the obvious choice.',
            'With OrderOrbit Space you can offer mix & match routines, multi-packs with a per-unit saving, and progressive gifts — all priced at checkout, with inventory deducted per product.',
        ],
        'setup' => [
            ['Mix & match routine bundle', 'Shoppers pick three products from your range and save as the bundle fills.'],
            ['Multi-pack quantity breaks', 'Buy 2 or 3 of the same product with a clear per-unit saving.'],
            ['Gift at the right threshold', 'A sample or travel size unlocks just above your average order.'],
        ],
        'later' => ['Reorder reminder emails', 'One-tap reorder in customer accounts'],
        'features' => ['bundles', 'progressive-gifts', 'countdown-timer', 'analytics'],
        'faqs' => [
            ['Do bundles work with my inventory?', 'Yes. Orders list each product, so Shopify deducts stock item by item.'],
            ['Can shoppers choose flavours or shades?', 'Yes. You map which variants each product offers and shoppers pick one per item.'],
        ],
    ],

    'fashion-apparel' => [
        'name' => 'Fashion & Apparel',
        'tagline' => 'Complete the look',
        'icon' => 'shirt',
        'visual' => 'bundles',
        'h1' => 'Sell the outfit, not the single item.',
        'hero' => 'Frequently-bought-together bundles, size selection per item, gifts and trust blocks for fashion and apparel stores.',
        'seo_description' => 'Complete-the-look bundles, per-item size selection, gifts and trust blocks for Shopify fashion brands.',
        'overview' => [
            'Fashion shoppers think in outfits. A frequently-bought-together bundle — tee, overshirt and cap — shows them the full look and makes it easy to buy in one step, with a size picker for every item.',
            'Add a free gift above your average order and a trust row about returns and exchanges, and the bigger basket stops feeling risky.',
        ],
        'setup' => [
            ['Complete-the-look bundle', 'A fixed bundle of matching items with one saving, a size choice per item.'],
            ['Gift above your average order', 'A tote or accessory unlocks when the cart passes the threshold.'],
            ['Returns and exchanges trust row', 'The reassurance fashion shoppers look for, next to the buy button.'],
        ],
        'later' => ['Personalized offers for returning customers'],
        'features' => ['bundles', 'progressive-gifts', 'trust-social-proof', 'sticky-add-to-cart'],
        'faqs' => [
            ['Can shoppers pick a different size for each item?', 'Yes. Each item in the bundle has its own variant picker.'],
        ],
    ],

    'shopify-plus' => [
        'name' => 'Shopify Plus Brands',
        'tagline' => 'Scale with control',
        'icon' => 'building',
        'visual' => 'analytics',
        'h1' => 'Grow order value with control and clear numbers.',
        'hero' => 'Team roles, an audit log, checkout-level pricing and per-offer revenue for larger Shopify stores.',
        'seo_description' => 'Bundles, gifts and upsells with team roles, audit logs and per-offer revenue for Shopify Plus brands.',
        'overview' => [
            'Larger stores need more than a widget. OrderOrbit Space gives your team owner, admin and staff roles, records every change in an audit log, and applies every saving through Shopify\'s own checkout functions.',
            'Analytics credit each order line to the offer that added it, so merchandising and growth teams can agree on what\'s working.',
        ],
        'setup' => [
            ['Team roles and audit log', 'Control who can publish, and see who changed what.'],
            ['Bundles and gifts across your catalogue', 'Unlimited offers on higher plans.'],
            ['Per-offer revenue', 'Views, adds to cart, orders and revenue for each offer.'],
        ],
        'later' => ['Blocks inside checkout (Plus)', 'A/B testing', 'Personalization by audience'],
        'features' => ['bundles', 'progressive-gifts', 'analytics', 'checkout'],
        'faqs' => [
            ['Does it use Shopify\'s own checkout pricing?', 'Yes. Savings are applied by Shopify Functions at checkout, so they work with your checkout settings.'],
        ],
    ],

];
