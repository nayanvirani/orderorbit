<?php

/*
| Documentation guides (/docs/{slug}). Each guide has sections; each section is a list of
| blocks: ['p', text], ['list', [items]], ['steps', [[title, text], ...]], ['table', [head],
| [[row], ...]], ['note', text], ['code', text]. Text may contain **bold**. The A/B testing
| guide has its own view (site/docs/ab-testing.blade.php) and is listed here for the hub.
*/

return [
    'getting-started' => [
        'title' => 'Getting started',
        'summary' => 'Install the app, choose a plan and goal, publish your first experience and check that analytics work.',
        'icon' => 'rocket',
        'sections' => [
            'install' => ['Install and connect', [
                ['p', 'Install OrderOrbit Space from the Shopify App Store and approve the permissions. The app opens inside your Shopify admin. It reads your products and orders, adds blocks to your theme and checkout, and connects a consent-aware analytics pixel automatically.'],
                ['note', 'If the app later asks for new permissions (for example after an update), open it once and approve them. Settings → Store shows any missing permissions.'],
            ]],
            'plan' => ['Choose a plan', [
                ['p', 'Every plan includes the core storefront widgets and every template. Plans differ by your store\'s monthly sales and by the advanced modules they include.'],
                ['table', ['Plan', 'Monthly store sales', 'Adds'], [
                    ['Free', 'Up to $1,000', 'Core widgets; one live bundle, gift campaign, cart upsell and pre-order'],
                    ['Starter', 'Up to $8,000', 'Everything unlimited; revenue per offer'],
                    ['Growth', 'Up to $20,000', 'Checkout, Thank You and account blocks, A/B testing, funnels, attribution and journeys'],
                    ['Scale', 'Unlimited', 'Lifecycle automation, audiences and personalization, priority support'],
                ]],
                ['p', 'Sales are counted per 30-day cycle from the day you install. If your store passes its plan\'s limit you have 3 days to upgrade before offers pause; nothing is deleted.'],
            ]],
            'onboarding' => ['Follow the eight onboarding steps', [
                ['steps', [
                    ['Store connection', 'Confirm the connection and permissions.'],
                    ['Goal', 'Choose what to improve first: conversion, order value, repeat purchases or checkout.'],
                    ['First experience', 'Pick one of the experiences recommended for your goal.'],
                    ['Template', 'Choose a layout. You can switch later without losing content.'],
                    ['Configure', 'Add products, text and the offer; match your brand in Design.'],
                    ['Preview', 'Check it on desktop and mobile in the builder.'],
                    ['Publish and place', 'Publish, then add the block in the Theme Editor (or checkout editor).'],
                    ['Verify analytics', 'Open your store and view a product; the first event appears within minutes.'],
                ]],
            ]],
            'embed' => ['Turn on the app embed', [
                ['p', 'In Shopify go to **Online Store → Themes → Customize → App embeds** and turn on **OrderOrbit Space**. The embed loads the small storefront runtime, places experiences set to appear above or below the add-to-cart button, and shows site-wide widgets such as Sales pop and sticky add to cart.'],
                ['note', 'Theme blocks need an Online Store 2.0 theme. The dashboard warns you if your theme doesn\'t support app blocks.'],
            ]],
            'next' => ['Where to go next', [
                ['list', ['**Home** shows revenue from offers, health alerts, top experiences and recommended next steps.', '**CRO** holds every storefront and checkout experience.', '**Analytics** measures everything; **A/B tests** prove what works; **Audiences** personalize; **Automation** follows up.', '**Support** opens a ticket with your store\'s details attached.']],
            ]],
        ],
    ],

    'bundles' => [
        'title' => 'Bundles',
        'summary' => 'Quantity breaks, variant offers, mix & match and fixed bundles, with real prices at checkout.',
        'icon' => 'bundle',
        'sections' => [
            'types' => ['Bundle types', [
                ['table', ['Type', 'Use it for'], [
                    ['Quantity breaks', 'Buy more, save more on one product (2-pack, 3-pack…).'],
                    ['Quantity breaks + gifts', 'The same, with a free gift on bigger packs.'],
                    ['Variant offers', 'Discounts when shoppers pick several variants (colours, flavours).'],
                    ['Bundle builder & mix and match', 'Shoppers choose items from a set of products or a collection.'],
                    ['Fixed bundle', 'A set of products sold together at a bundle price.'],
                    ['Fixed bundle + gifts', 'A fixed set plus a free gift.'],
                ]],
            ]],
            'create' => ['Create a bundle', [
                ['steps', [
                    ['Choose the type and a model', 'CRO → Bundles → Create. Each type has ready-made models with sensible tiers.'],
                    ['Set the offers', 'Quantities, discount type (percentage, fixed amount or fixed price), labels such as "Best value", which tier is preselected, and gifts.'],
                    ['Pick products and variants', 'Choose which variants shoppers can select; each item gets its own selector on the storefront.'],
                    ['Design', 'Colours, layout and text. The preview updates as you edit.'],
                    ['Publish', 'The bundle appears on its product pages next to the add-to-cart button, or wherever you place its block.'],
                ]],
            ]],
            'checkout' => ['How bundles reach checkout', [
                ['p', 'A bundle is added as one cart line at the bundle price. A Shopify cart transform merges the items, so the order still lists every product and Shopify deducts inventory from each. Prices are applied by Shopify, so they can\'t be edited in the browser.'],
                ['note', 'When a bundle shows on a product page it replaces the theme\'s variant picker and add-to-cart, so shoppers can\'t add the product twice. Pause the bundle and the theme\'s own buttons come back.'],
            ]],
            'measure' => ['Measure', [
                ['p', 'Each bundle\'s page shows views, adds to cart, orders and the revenue from the lines it added. Analytics → Funnels has a ready-made Bundle funnel.'],
            ]],
        ],
    ],

    'progressive-gifts' => [
        'title' => 'Progressive gifts',
        'summary' => 'Free shipping, gifts and discounts that unlock as the cart grows.',
        'icon' => 'gift',
        'sections' => [
            'how' => ['How it works', [
                ['p', 'A campaign has milestones. Each milestone unlocks at a cart value or item count and gives a reward: **free shipping**, a **free gift**, a **choice of gifts** or an **order discount**. A progress bar shows how close the shopper is to the next reward.'],
            ]],
            'set' => ['Set up a campaign', [
                ['steps', [
                    ['Choose a layout', 'CRO → Progressive gifts → Create, then a layout such as the milestone bar or reward cards.'],
                    ['Add milestones', 'Pick "by cart value" or "by number of items", then add up to several milestones, each with its reward and label.'],
                    ['Choose gifts', 'For gift rewards pick the products (and quantities). With a choice of gifts, shoppers choose one.'],
                    ['Write the messages', 'Progress ("Add {remaining} more to unlock {reward}!") and unlocked messages.'],
                    ['Publish', 'The bar shows on product pages and in the cart; place its block where you want it.'],
                ]],
            ]],
            'rules' => ['Rules shoppers can rely on', [
                ['list', ['Rewards apply automatically at checkout while the cart qualifies; no discount code is needed.', 'If the cart drops below a threshold, the reward and any free gift added for it are removed.', 'Checkout blocks for shipping progress and free gifts follow the same live campaign, so storefront and checkout always agree.']],
                ['note', 'Tip: set the first gift milestone just above your current average order value (Analytics shows it).'],
            ]],
        ],
    ],

    'storefront-widgets' => [
        'title' => 'Storefront widgets',
        'summary' => 'Cart upsells, countdowns, sticky add to cart, trust badges, sales pop and pre-orders.',
        'icon' => 'sparkle',
        'sections' => [
            'all' => ['The widgets', [
                ['table', ['Widget', 'What it does', 'Where it shows'], [
                    ['Cart upsells', 'One last add-on in the cart, with an optional discount on items added from it.', 'Cart page block'],
                    ['Countdown timer', 'A real deadline: a date, a daily cutoff in your time zone, or hour and minute timers.', 'Any page block'],
                    ['Sticky add to cart', 'Keeps the buy button in reach after the theme\'s button scrolls away.', 'Product pages (app embed)'],
                    ['Trust badges', 'Reviews, rating strips, trust rows and guarantees.', 'Any page block'],
                    ['Sales pop', 'Small notifications of real recent purchases (product, country, time; never names).', 'Every page (app embed)'],
                    ['Pre-orders', 'Sell out-of-stock products with a ship date and progress.', 'Product page block'],
                ]],
            ]],
            'build' => ['Build any widget', [
                ['steps', [
                    ['Type and template', 'CRO → choose the feature → Create, then pick a template.'],
                    ['Content', 'Text, products, deadlines and offers.'],
                    ['Design', 'Colours, corners, border, spacing, fonts, mobile and desktop visibility, and optional scoped custom CSS.'],
                    ['Behavior and targeting', 'Animation, dismissible, priority; page types, products, collections, cart value, device, new or returning shoppers, countries, UTM, schedule and segments.'],
                    ['Publish and place', 'Add the OrderOrbit Space block in the Theme Editor. Widgets set to appear above or below add to cart are placed automatically by the app embed.'],
                ]],
                ['note', 'Countdowns only count to real deadlines; they never reset per visitor to fake urgency.'],
            ]],
        ],
    ],

    'checkout-blocks' => [
        'title' => 'Checkout, Thank You and post-purchase',
        'summary' => 'Blocks inside checkout, on the Thank You and Order Status pages, and a one-click offer after payment.',
        'icon' => 'card',
        'sections' => [
            'blocks' => ['The blocks', [
                ['table', ['Page', 'Blocks', 'Shopify plan'], [
                    ['Checkout', 'Reviews, countdown, shipping progress, free gift, promotion, trust, image', 'Shopify Plus (development stores can preview)'],
                    ['Thank You and Order Status', 'Cross-sell, reorder, review request, referral, survey, next-order code, message and links, image', 'Every plan'],
                    ['Post-purchase', 'A one-click offer between payment and the Thank You page, with an optional second offer', 'Every plan'],
                ]],
                ['p', 'These are on the Growth and Scale plans.'],
            ]],
            'place' => ['Create and place a block', [
                ['steps', [
                    ['Create', 'CRO → Checkout blocks (or Thank You & Order Status) → create a block and pick a layout.'],
                    ['Style it', 'Width, height, background, border, corners and text tone. Shopify doesn\'t allow custom colours in checkout, so colours come from your checkout branding.'],
                    ['Publish', 'The app publishes the block for the checkout extension.'],
                    ['Place it', 'In Shopify open **Settings → Checkout → Customize**, choose the page (Checkout, Thank You or Order Status), add the **OrderOrbit Space** block and set its type. To show one specific block, paste its Experience ID.'],
                ]],
            ]],
            'postpurchase' => ['Post-purchase offer', [
                ['p', 'Create the funnel under CRO → Post-purchase, choose when it shows (products, order value), the offer and its discount, and an optional second offer if declined. Then in Shopify choose OrderOrbit Space under **Settings → Checkout → Post-purchase page**. Accepting adds the item to the paid order and charges the same payment method.'],
            ]],
            'measure' => ['Measure and test', [
                ['p', 'Blocks record views and clicks; survey answers appear on the survey\'s page. Checkout and Thank You blocks can be A/B tested (Thank You blocks by click-through rate).'],
            ]],
        ],
    ],

    'customer-accounts' => [
        'title' => 'Customer accounts',
        'summary' => 'My orders, tracking, reorder, rewards, reviews, purchased products and support in Shopify customer accounts.',
        'icon' => 'user',
        'sections' => [
            'need' => ['Requirements', [
                ['list', ['Shopify\'s **new customer accounts** (Settings → Customer accounts). Classic accounts can\'t show app blocks; the app tells you if your store uses them.', 'The Growth or Scale plan.']],
            ]],
            'blocks' => ['The blocks', [
                ['table', ['Block', 'Shows'], [
                    ['My orders', 'Order count and spend, the latest order with status and tracking, buy again'],
                    ['Track order', 'Shipment progress, carrier, tracking links and estimated delivery'],
                    ['Reorder', 'Buy again with all items or chosen items; also adds "Buy again" to each order\'s menu'],
                    ['My rewards', 'Tiers by total spend with progress to the next tier and its perks'],
                    ['My reviews', 'Products the customer bought with a "Write a review" link'],
                    ['My products', 'A shelf of purchased products with buy again and product links'],
                    ['Support', 'Email, phone, help and returns links, and FAQs'],
                ]],
            ]],
            'place' => ['Place a block', [
                ['steps', [
                    ['Create and publish', 'CRO → Customer accounts → create a block, pick a layout, set its content and style, publish.'],
                    ['Add it in Shopify', 'Settings → Checkout → Customize, switch to the **Orders**, **Profile** or **Order status** page, add the **OrderOrbit Space account** block and choose its type.'],
                ]],
                ['note', 'Each block reads the signed-in customer\'s own orders, so everyone sees only their own data.'],
            ]],
        ],
    ],

    'automation' => [
        'title' => 'Lifecycle automation',
        'summary' => 'Workflows that follow up after orders, deliveries, refunds and new customers.',
        'icon' => 'flow',
        'sections' => [
            'parts' => ['Triggers, conditions and actions', [
                ['table', ['Part', 'Options'], [
                    ['Triggers', 'Order created, paid, fulfilled, delivered, cancelled; refund created; customer created; customer tag added; product purchased; OrderOrbit events'],
                    ['Conditions', 'Order total, quantity, product, variant, SKU, collection, new or returning, customer tag, country and province, shipping and payment method, fulfillment status, previous orders, LTV, days since order, ordered again, event properties, customer segment'],
                    ['Actions', 'Prepare an email, notify your team, create a task, add or remove order and customer tags, create a single-use discount, send a webhook, start another workflow'],
                    ['Flow', 'Waits (minutes, hours, days) and if/else branches'],
                ]],
            ]],
            'build' => ['Build a workflow', [
                ['steps', [
                    ['Start from a template', 'Automation → Templates: review request, delivery follow-up, welcome, VIP, reorder, win-back, cross-sell, product education, refund and cancellation follow-ups.'],
                    ['Edit on the canvas', 'Change the trigger, add conditions, waits, branches and actions. Placeholders such as {{order_name}} and {{discount_code}} fill in per order.'],
                    ['Test', '"Test with a sample order" walks through every step and changes nothing.'],
                    ['Publish', 'Workflows run on the Scale plan. Every publish is saved as a version you can restore.'],
                ]],
            ]],
            'runs' => ['Runs, retries and logs', [
                ['list', ['Each Shopify event starts a workflow once, even if Shopify sends it again.', 'Failed steps retry after 5 and 30 minutes; finished steps never repeat. You can retry a failed run from its page.', 'Runs show trigger, status, duration, idempotency key and a step-by-step log; filter by status or workflow.', 'Tasks and notifications appear in Automation → Inbox.']],
                ['note', 'Email steps prepare each message and keep it under Automation → Emails. Sending through an email provider is being connected.'],
            ]],
        ],
    ],

    'analytics' => [
        'title' => 'Analytics',
        'summary' => 'Store totals, revenue per offer, Event Explorer, funnels, attribution and customer journeys.',
        'icon' => 'chart',
        'sections' => [
            'collect' => ['What is collected', [
                ['p', 'A Shopify web pixel records, only for shoppers who allow analytics: sessions, Shopify\'s storefront events (pages, products, collections, search, cart, checkout, payment, purchase) and every OrderOrbit Space view, click, add to cart and reward. Each event carries an anonymous visitor id, session, device, page type and traffic source (UTM tags, click ids or referrer). No names, emails or addresses are stored.'],
            ]],
            'reports' => ['Reports', [
                ['table', ['Report', 'Answers', 'Plan'], [
                    ['Overview', 'Revenue, orders, AOV, conversion, revenue from offers, per-offer results', 'All (revenue per offer from Starter)'],
                    ['Event Explorer', 'Every event with counts, visitors, sessions, trend; break down by experience, template, product, device, market, UTM, A/B test', 'Growth and Scale'],
                    ['Funnels', 'Step conversion, drop-off, time between steps; compare by device, source or period', 'Growth and Scale'],
                    ['Revenue & attribution', 'Revenue by source and campaign (first or last touch), by experience (direct and assisted), by template and A/B variant', 'Growth and Scale'],
                    ['Customer journeys', 'Each shopper\'s visits, offers, cart steps, purchases and automations', 'Growth and Scale'],
                ]],
                ['p', 'Filter reports by device, market, UTM and experience, and export them as CSV.'],
            ]],
            'privacy' => ['Privacy controls', [
                ['p', 'Settings → Privacy lets you keep events for 3, 6 or 13 months, turn off browsing events or customer journeys, export all analytics and delete everything. Shopify\'s customer deletion requests are handled automatically.'],
                ['note', 'Attribution is an analytical model, not proof that something caused a sale. Use A/B tests to prove impact.'],
            ]],
        ],
    ],

    'personalization' => [
        'title' => 'Audiences & personalization',
        'summary' => 'Segments and rules that show the right experience to each shopper.',
        'icon' => 'target',
        'sections' => [
            'segments' => ['Segments', [
                ['p', 'Audiences → Segments. Start from ten ready-made segments (new, returning, first-time, high-AOV, VIP, product purchasers, experience interactors, at-risk, lapsed, mobile) or build your own with all/any rules on orders, total spent, average order value, days since last order, signed in, customer tag, products bought, market, device and experiences used.'],
                ['p', 'Segments made only of customer fields show a member count from Shopify. Each segment lists where it\'s used, and can\'t be archived while in use.'],
            ]],
            'rules' => ['Personalization rules', [
                ['steps', [
                    ['Pick the experience', 'Any storefront experience except Bundles and Progressive gifts.'],
                    ['Choose the audience', 'One or more segments, plus optional device, cart value or UTM conditions.'],
                    ['Choose the outcome', 'Show it only to them, show it in another template, or hide it.'],
                    ['Order the rules', 'For each experience, the first matching rule wins. Conflicts are flagged.'],
                ]],
                ['p', 'Examples: returning + cart over $75 → show the premium upsell; mobile → compact template; 3+ orders → VIP offer.'],
            ]],
            'where' => ['Use segments everywhere', [
                ['list', ['Experience targeting: Only these segments.', 'A/B test audiences.', 'Workflow condition: Customer segment (checks the order\'s customer).']],
                ['note', 'Segments are worked out in the shopper\'s browser from their own account and visit; nothing about them is sent to OrderOrbit Space. Personalization is on the Scale plan.'],
            ]],
        ],
    ],

    'ab-testing' => [
        'title' => 'A/B testing',
        'summary' => 'Set up, run and read A/B and A/B/C tests, and the statistics behind a winner.',
        'icon' => 'split',
        'sections' => [],
    ],

    'developers' => [
        'title' => 'Developers: theme callbacks',
        'summary' => 'Run your own theme code when shoppers use an OrderOrbit Space offer.',
        'icon' => 'tool',
        'sections' => [
            'hooks' => ['Callbacks', [
                ['p', 'Define functions on **window.OrderOrbitHooks** in your theme, or listen for the matching events on document. Nothing is stored in the app.'],
                ['code', "window.OrderOrbitHooks = {\n  beforeAddToCart: function (detail) { /* before items are sent */ },\n  afterAddToCart:  function (detail) { /* after a successful add; return false to stay on the page */ },\n  addToCartFailed: function (detail) { /* when Shopify refuses the add */ }\n};\n\ndocument.addEventListener('orderorbit:added-to-cart', (e) => console.log(e.detail));"],
            ]],
            'detail' => ['The detail object', [
                ['table', ['Field', 'Meaning'], [
                    ['detail.experience', 'The offer used: id, type and template'],
                    ['detail.items', 'Items being added: variant id, quantity, line properties'],
                    ['detail.after', 'What happens next: "cart", "checkout" or "stay"'],
                    ['detail.element', 'The widget on the page'],
                    ['detail.response', 'Shopify\'s reply, after a successful add'],
                    ['detail.getCart()', 'Returns the live cart'],
                ]],
            ]],
            'drawer' => ['Open your cart drawer', [
                ['p', 'Use afterAddToCart, redraw your theme\'s drawer and return false so the shopper stays on the page. For Dawn-based themes:'],
                ['code', "window.OrderOrbitHooks = {\n  afterAddToCart: async function (detail) {\n    const drawer = document.querySelector('cart-drawer');\n    if (!drawer) return;\n    const ids = drawer.getSectionsToRender().map((s) => s.id);\n    const res = await fetch('/?sections=' + ids.join(','));\n    drawer.renderContents({ sections: await res.json() });\n    return false;\n  }\n};"],
                ['note', 'OrderOrbit Space never opens or intercepts your cart drawer on its own; callbacks are how your theme takes over.'],
            ]],
        ],
    ],
];
