<?php

/*
| Help center topics. Each topic has a short intro and [question, answer]
| articles. Only live features are covered; planned ones are listed on the
| features pages as coming soon.
*/

return [
    [
        'name' => 'Getting started',
        'icon' => 'rocket',
        'text' => 'Install the app, choose a plan and publish your first offer.',
        'articles' => [
            ['How do I install OrderOrbit Space?', 'Open the listing in the Shopify App Store and click Install. Approve the permissions, choose a plan and you land on the app dashboard inside your Shopify admin.'],
            ['What should I set up first?', 'Start with one bundle on your best-selling product and a progressive gifts bar with free shipping at your current free-shipping threshold. Together they usually give the quickest lift in order value.'],
            ['Do I need to enable the app in my theme?', 'Yes, once. In your Shopify admin go to Online Store → Themes → Customize → App embeds and turn on OrderOrbit Space. The dashboard shows a reminder until it is on.'],
        ],
    ],
    [
        'name' => 'Theme setup',
        'icon' => 'layers',
        'text' => 'Where offers appear and how to place blocks in your theme.',
        'articles' => [
            ['Where do bundles and gift bars appear?', 'Bundles and progressive gifts show on the product page next to your add-to-cart button automatically. You choose above or below the button in the offer settings.'],
            ['How do I place a countdown, trust block or upsell?', 'Open the Theme Editor, go to the page you want, click Add block and choose the OrderOrbit Space block. Drag it where you want it and save.'],
            ['Will the app change my theme code?', 'No. Everything runs through Shopify theme app extensions, so removing a block or uninstalling the app leaves your theme exactly as it was.'],
        ],
    ],
    [
        'name' => 'Templates and design',
        'icon' => 'sparkle',
        'text' => 'Pick a layout and make it match your store.',
        'articles' => [
            ['What is a template?', 'A ready-made layout for a feature, such as the stacked cards bundle or the milestone gift bar. You pick one when you create an offer and can switch at any time.'],
            ['Can I change colours and text?', 'Yes. Every template lets you change colours, corner radius, spacing, font sizes and all visible text. The preview updates as you edit, on desktop and mobile.'],
            ['Does it use my store fonts?', 'Yes. Offers inherit your theme\'s fonts by default, so they look like part of your store.'],
        ],
    ],
    [
        'name' => 'Bundles',
        'icon' => 'bundle',
        'text' => 'Bundle types, variants, pricing and inventory.',
        'articles' => [
            ['Which bundle types can I create?', 'Quantity breaks, quantity breaks with a free gift, variant offers, mix & match, fixed bundles and fixed bundles with a free gift.'],
            ['How do variants work?', 'For each product in a bundle you choose which variants shoppers can pick. On the storefront every item gets its own size or colour selector.'],
            ['How does a bundle look in the cart?', 'As one line at the bundle price. The order still lists each product, so Shopify deducts inventory from every item and fulfilment works as usual.'],
            ['Why is my theme\'s add-to-cart hidden?', 'When a bundle shows on a product page it replaces your theme\'s variant picker and add-to-cart, so shoppers can\'t add the product twice. Pause the bundle and your theme\'s form comes back.'],
        ],
    ],
    [
        'name' => 'Progressive gifts',
        'icon' => 'gift',
        'text' => 'Free gifts, free shipping and discounts that unlock as the cart grows.',
        'articles' => [
            ['What rewards can I offer?', 'Free shipping, a free gift, a choice of gifts or an order discount. Each unlocks at its own threshold by cart value or by number of items.'],
            ['What happens if the cart drops below a threshold?', 'The reward is removed, including any free gift that was added, so nobody gets a free item without qualifying.'],
            ['Do shoppers need a discount code?', 'No. Rewards apply automatically at checkout while the cart qualifies.'],
        ],
    ],
    [
        'name' => 'Upsells, countdowns, pre-orders and more',
        'icon' => 'clock',
        'text' => 'Cart upsells, countdown timers, pre-orders, sales pop, sticky add-to-cart and trust blocks.',
        'articles' => [
            ['How do cart upsell incentives work?', 'If you add a discount to an upsell, it applies only to items added from that recommendation, automatically at checkout.'],
            ['Can a countdown reset for each visitor?', 'No. Countdowns only count to a real end date or a daily cutoff in your store\'s time zone. When the campaign ends the timer hides or shows your message.'],
            ['How do pre-orders work?', 'Pick the products and the ship date, then add the OrderOrbit Space block to your product template and choose "Pre-order". Turn on "Continue selling when out of stock" for those products in Shopify. Each pre-order item gets a "Pre-order: Ships by …" line on the order.'],
            ['Where does Sales pop get its purchases?', 'From your store\'s real recent orders: the product, the order\'s country and the time. No names are shown and nothing is invented. It shows on every page while the app embed is on.'],
            ['When does the sticky add-to-cart show?', 'After your theme\'s own buy button scrolls out of view, on the devices you choose. It disappears again when the button is back on screen.'],
        ],
    ],
    [
        'name' => 'Analytics',
        'icon' => 'chart',
        'text' => 'What is measured and how revenue is credited.',
        'articles' => [
            ['How is revenue from offers measured?', 'A Shopify web pixel records completed orders. Each order line that was added by a bundle, gift or upsell is credited to that offer.'],
            ['Why do my numbers differ from Shopify reports?', 'Analytics only count shoppers who allow analytics in your consent banner, and they start from the day the app was installed.'],
            ['How long is data kept?', 'Analytics events are kept for 13 months and then deleted automatically.'],
        ],
    ],
    [
        'name' => 'Billing and plans',
        'icon' => 'card',
        'text' => 'Plans, limits, upgrades and cancelling.',
        'articles' => [
            ['How am I billed?', 'Through your Shopify invoice. There is no separate card to add.'],
            ['Can I change plans?', 'Yes, at any time from Plans in the app. Upgrades take effect straight away.'],
            ['What happens if I uninstall?', 'Billing stops, offers are removed from your store and checkout pricing stops. Your store data is deleted as Shopify requires.'],
        ],
    ],
    [
        'name' => 'Troubleshooting',
        'icon' => 'tool',
        'text' => 'Offers not showing, prices not applying and other fixes.',
        'articles' => [
            ['My offer doesn\'t show on the store.', 'Check that the offer is published, the app embed is on in the Theme Editor, and the offer is assigned to the product you are viewing. Then reload the page without cache.'],
            ['The bundle price isn\'t applied at checkout.', 'Make sure the offer is published and the product is still active. If you changed products in the bundle, save and publish again.'],
            ['Still stuck?', 'Open Support in the app — your store details are included automatically — or use the contact form on this site.'],
        ],
    ],
];
