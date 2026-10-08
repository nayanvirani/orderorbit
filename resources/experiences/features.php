<?php

/*
|--------------------------------------------------------------------------
| App features (navigation)
|--------------------------------------------------------------------------
|
| What merchants see in the app menu and on the dashboard. Each feature
| groups one or more experience types (resources/experiences/types.php).
| "steps" explain how the feature works on the feature page.
|
*/

return [

    'bundles' => [
        'icon' => 'package',
        'tone' => 'bundles',
        'label' => 'Bundles',
        'types' => ['bundles'],
        'module' => 'app.bundles.index',
        'tagline' => 'Sell more per order with <em>bundles.</em>',
        'lead' => 'Mix & match, fixed bundles, frequently bought together and gift boxes. Shoppers add the whole bundle in one click and the saving applies automatically at checkout.',
        'steps' => [
            'Pick the products, bundle type and saving.',
            'Add the Growvia block to your product page in the Theme Editor.',
            'Shoppers add the bundle in one click; the discount applies in cart and checkout.',
        ],
    ],

    'progressive-gifts' => [
        'icon' => 'gift',
        'tone' => 'gifts',
        'label' => 'Progressive gifts',
        'types' => ['progressive-gifts'],
        'module' => 'app.gifts.index',
        'tagline' => 'Rewards that grow <em>with the cart.</em>',
        'lead' => 'Free gifts, free shipping and discounts that unlock by cart value or item count, in one progress bar.',
        'steps' => [
            'Set your milestones and their rewards.',
            'Pick a layout: bar, strip, steps, cards or radial.',
            'It shows under the add to cart button; rewards apply at checkout.',
        ],
    ],

    'cart-upsells' => [
        'icon' => 'cart',
        'tone' => 'upsells',
        'label' => 'Cart upsells',
        'types' => ['cart-upsells'],
        'tagline' => 'One last add-on <em>in the cart.</em>',
        'lead' => 'Cart recommendations with one-click add. An optional incentive applies only to items added from the offer.',
        'steps' => [
            'Choose the products to recommend and an optional % off.',
            'Place the block in your cart.',
            'Shoppers add with one click; the incentive applies at checkout.',
        ],
    ],

    'countdown' => [
        'icon' => 'clock',
        'tone' => 'countdown',
        'label' => 'Countdown timer',
        'types' => ['countdown'],
        'tagline' => 'Real deadlines for <em>real campaigns.</em>',
        'lead' => 'Countdown timers tied to a real end date. Timers never reset per visitor and hide or show a message when the campaign ends.',
        'steps' => [
            'Set the campaign end date and message.',
            'Place the timer on any page.',
            'It hides or changes its message automatically when time is up.',
        ],
    ],

    'sticky-atc' => [
        'icon' => 'cursor',
        'tone' => 'sticky',
        'label' => 'Sticky add to cart',
        'types' => ['sticky-atc'],
        'tagline' => 'Keep the buy button <em>in reach.</em>',
        'lead' => 'A slim add-to-cart bar that appears once your theme\'s buy button scrolls out of view, using your theme\'s own cart behaviour.',
        'steps' => [
            'Choose what the bar shows: image, price, button text.',
            'Place the block on your product template.',
            'The bar slides in when shoppers scroll past the buy button.',
        ],
    ],

    'preorder' => [
        'icon' => 'calendar',
        'tone' => 'preorder',
        'label' => 'Pre-order',
        'types' => ['preorder'],
        'tagline' => 'Sell it before it\'s <em>in stock.</em>',
        'lead' => 'A pre-order widget for the products you choose, with the ship date, a progress bar and the time left in months, weeks or days. It can relabel your add-to-cart and marks pre-order items on the order.',
        'steps' => [
            'Pick the products, the ship date and a layout.',
            'Add the Growvia block to your product template and choose "Pre-order".',
            'Shoppers see the ship date and order; each item is marked "Pre-order" in the cart and on the order.',
        ],
    ],

    'checkout' => [
        'icon' => 'checkout',
        'tone' => 'checkout',
        'label' => 'Checkout blocks',
        'types' => ['checkout-reviews', 'checkout-countdown', 'checkout-shipping', 'checkout-gift', 'checkout-promo', 'checkout-trust', 'checkout-upsell', 'checkout-addon', 'checkout-image'],
        'tagline' => 'Keep selling <em>inside checkout.</em>',
        'lead' => 'Reviews, countdowns, shipping progress, free gifts, promotions, trust and images, shown inside Shopify checkout. Blocks inside checkout need Shopify Plus; Shopify doesn\'t allow them on other plans.',
        'steps' => [
            'Create a block and pick a layout. Set its size, background, border, corners and text colour; colours come from your checkout branding.',
            'In Shopify, open Settings → Checkout → Customize and add the Growvia block where you want it.',
            'Choose the block type in its settings. Shipping progress and free gifts follow your Progressive gifts campaign.',
        ],
    ],

    'thank-you' => [
        'icon' => 'star',
        'tone' => 'thankyou',
        'label' => 'Thank You & Order Status',
        'types' => ['ty-cross-sell', 'ty-reorder', 'ty-review', 'ty-referral', 'ty-survey', 'ty-discount', 'ty-message', 'ty-image'],
        'tagline' => 'The order is placed. <em>Start the next one.</em>',
        'lead' => 'Cross-sells, reorder, review requests, referrals, surveys, next-order codes, images and helpful messages on the Thank You and Order Status pages. Works on every Shopify plan.',
        'steps' => [
            'Create a block and pick a layout.',
            'In Shopify, open Settings → Checkout → Customize, switch to the Thank You or Order Status page and add the Growvia block.',
            'Choose the block type in its settings. Survey answers appear in Analytics.',
        ],
    ],

    'post-purchase' => [
        'icon' => 'bolt',
        'tone' => 'postpurchase',
        'label' => 'Post-purchase funnel',
        'types' => ['post-purchase'],
        'tagline' => 'One more yes, <em>after they pay.</em>',
        'lead' => 'A one-click offer between payment and the Thank You page. Shoppers add it to the order they just paid for without entering their card again; if they decline, you can show a second offer.',
        'steps' => [
            'Choose when it shows, the offer and its discount, and an optional second offer.',
            'In Shopify, open Settings → Checkout and choose Growvia under Post-purchase page.',
            'After paying, shoppers see the offer; accepting adds it to their order and charges the same payment.',
        ],
    ],

    'customer-accounts' => [
        'icon' => 'users',
        'tone' => 'thankyou',
        'label' => 'Customer accounts',
        'types' => ['account-orders', 'account-tracking', 'account-reorder', 'account-rewards', 'account-reviews', 'account-products', 'account-support'],
        'tagline' => 'Turn customer accounts into <em>repeat sales.</em>',
        'lead' => 'My orders, order tracking, one-tap reorder, rewards, reviews, purchased products and support, inside Shopify\'s customer accounts. Needs Shopify\'s new customer accounts.',
        'steps' => [
            'Create a block and pick a layout. Set its size, background, border and corners.',
            'In Shopify, open Settings → Checkout → Customize, switch to the Orders, Profile or Order status page and add the Growvia account block.',
            'Choose the block type in its settings. Customers see it the next time they sign in.',
        ],
    ],

    'sales-pop' => [
        'icon' => 'users',
        'tone' => 'salespop',
        'label' => 'Sales pop',
        'types' => ['sales-pop'],
        'tagline' => 'Show what others <em>just bought.</em>',
        'lead' => 'Small recent-purchase notifications on every page, built from your real orders. No theme block needed: the app embed shows them wherever you choose.',
        'steps' => [
            'Pick a layout and the position for desktop and mobile.',
            'Set timing: first pop, auto close, gap and order.',
            'Publish. It shows on every page while the app embed is on, using your real recent orders.',
        ],
    ],

    'trust' => [
        'icon' => 'shield',
        'tone' => 'trust',
        'label' => 'Trust badges',
        'types' => ['trust'],
        'tagline' => 'Show shoppers why they can <em>trust you.</em>',
        'lead' => 'Reviews, ratings, trust rows and guarantees that match your brand.',
        'steps' => [
            'Add your rating, reviews, badges or guarantee.',
            'Pick a template: review cards, slider, rating strip or trust row.',
            'Place it wherever shoppers hesitate: product page, cart or home.',
        ],
    ],

];
