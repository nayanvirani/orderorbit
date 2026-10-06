<?php

/*
| Documentation guides (/docs/{slug}). Each guide has sections; each section is a list of
| blocks: ['p', text], ['list', [items]], ['steps', [[title, text], ...]], ['table', [head],
| [[row], ...]], ['note', text], ['code', text], ['h3', heading], ['faq', [[question, answer], ...]].
| Text may contain **bold**, *highlight* and [links](/path). Optional per guide: h1, lead, meta.
*/

return [
    'getting-started' => [
        'title' => 'Getting started',
        'summary' => 'Install the app, choose a plan and goal, publish your first widget and check that analytics work.',
        'icon' => 'rocket',
        'sections' => [
            'install' => ['Install and connect', [
                ['p', 'Install OrderOrbit Space from the Shopify App Store and approve the permissions. The app opens inside your Shopify admin. It reads your products and orders, adds blocks to your theme and checkout, and connects a consent-aware analytics pixel automatically.'],
                ['note', 'If the app later asks for new permissions (for example after an update), open it once and approve them. Settings → Store shows any missing permissions.'],
            ]],
            'plan' => ['Choose a plan', [
                ['p', 'Every plan includes the core storefront widgets and every template. Plans differ by the features they include and by how many offers, workflows and automation runs you can have. The Pricing page compares them in full.'],
                ['table', ['Plan', 'Price', 'Includes'], array_values(array_map(fn ($p) => [$p['name'], $p['price'] > 0 ? '$'.number_format($p['price'], 2).' a month' : 'Free', implode('; ', array_slice(\App\Support\Plans::lines($p), 0, 4))], \App\Support\Plans::public()))],
                ['p', 'The app warns you as you get close to a limit. At a limit, everything live keeps working; you just can\'t add more until you upgrade. Changing plan never deletes anything: features a lower plan doesn\'t include stop showing, and items over its limits are paused.'],
            ]],
            'onboarding' => ['Follow the eight onboarding steps', [
                ['steps', [
                    ['Store connection', 'Confirm the connection and permissions.'],
                    ['Goal', 'Choose what to improve first: conversion, order value, repeat purchases or checkout.'],
                    ['First widget', 'Pick one of the widgets recommended for your goal.'],
                    ['Template', 'Choose a layout. You can switch later without losing content.'],
                    ['Configure', 'Add products, text and the offer; match your brand in Design.'],
                    ['Preview', 'Check it on desktop and mobile in the builder.'],
                    ['Publish and place', 'Publish, then add the block in the Theme Editor (or checkout editor).'],
                    ['Verify analytics', 'Open your store and view a product; the first event appears within minutes.'],
                ]],
            ]],
            'embed' => ['Turn on the app embed', [
                ['p', 'In Shopify go to **Online Store → Themes → Customize → App embeds** and turn on **OrderOrbit Space**. The embed loads the small storefront runtime, places widgets set to appear above or below the add-to-cart button, and shows site-wide widgets such as Sales pop and sticky add to cart.'],
                ['note', 'Theme blocks need an Online Store 2.0 theme. The dashboard warns you if your theme doesn\'t support app blocks.'],
            ]],
            'next' => ['Where to go next', [
                ['list', ['**Home** shows revenue from offers, health alerts, top widgets and recommended next steps.', '**CRO** holds every storefront and checkout widget.', '**Analytics** measures everything; **A/B tests** prove what works; **Audiences** personalize; **Automation** follows up.', '**Support** opens a ticket with your store\'s details attached.']],
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
                ['p', 'These are included on the plans shown on the Pricing page.'],
            ]],
            'place' => ['Create and place a block', [
                ['steps', [
                    ['Create', 'CRO → Checkout blocks (or Thank You & Order Status) → create a block and pick a layout.'],
                    ['Style it', 'Width, height, background, border, corners and text tone. Shopify doesn\'t allow custom colours in checkout, so colours come from your checkout branding.'],
                    ['Publish', 'The app publishes the block for the checkout extension.'],
                    ['Place it', 'In Shopify open **Settings → Checkout → Customize**, choose the page (Checkout, Thank You or Order Status), add the **OrderOrbit Space** block and set its type. To show one specific block, paste its Widget ID.'],
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
                    ['Publish', 'Every publish is saved as a version you can restore. How many workflows can be on at once, and how many runs a month, depends on your plan.'],
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
                    ['Event Explorer', 'Every event with counts, visitors, sessions, trend; break down by widget, template, product, device, market, UTM, A/B test', 'Growth and Scale'],
                    ['Funnels', 'Step conversion, drop-off, time between steps; compare by device, source or period', 'Growth and Scale'],
                    ['Revenue & attribution', 'Revenue by source and campaign (first or last touch), by widget (direct and assisted), by template and A/B variant', 'Growth and Scale'],
                    ['Customer journeys', 'Each shopper\'s visits, offers, cart steps, purchases and automations', 'Growth and Scale'],
                ]],
                ['p', 'Filter reports by device, market, UTM and widget, and export them as CSV.'],
            ]],
            'privacy' => ['Privacy controls', [
                ['p', 'Settings → Privacy lets you keep events for 3, 6 or 13 months, turn off browsing events or customer journeys, export all analytics and delete everything. Shopify\'s customer deletion requests are handled automatically.'],
                ['note', 'Attribution is an analytical model, not proof that something caused a sale. Use A/B tests to prove impact.'],
            ]],
        ],
    ],

    'personalization' => [
        'title' => 'Audiences & personalization',
        'summary' => 'Segments and rules that show the right widget to each shopper.',
        'icon' => 'target',
        'sections' => [
            'segments' => ['Segments', [
                ['p', 'Audiences → Segments. Start from ten ready-made segments (new, returning, first-time, high-AOV, VIP, product purchasers, widget interactors, at-risk, lapsed, mobile) or build your own with all/any rules on orders, total spent, average order value, days since last order, signed in, customer tag, products bought, market, device and widgets used.'],
                ['p', 'Segments made only of customer fields show a member count from Shopify. Each segment lists where it\'s used, and can\'t be archived while in use.'],
            ]],
            'rules' => ['Personalization rules', [
                ['steps', [
                    ['Pick the widget', 'Any storefront widget except Bundles and Progressive gifts.'],
                    ['Choose the audience', 'One or more segments, plus optional device, cart value or UTM conditions.'],
                    ['Choose the outcome', 'Show it only to them, show it in another template, or hide it.'],
                    ['Order the rules', 'For each widget, the first matching rule wins. Conflicts are flagged.'],
                ]],
                ['p', 'Examples: returning + cart over $75 → show the premium upsell; mobile → compact template; 3+ orders → VIP offer.'],
            ]],
            'where' => ['Use segments everywhere', [
                ['list', ['Widget targeting: Only these segments.', 'A/B test audiences.', 'Workflow condition: Customer segment (checks the order\'s customer).']],
                ['note', 'Segments are worked out in the shopper\'s browser from their own account and visit; nothing about them is sent to OrderOrbit Space. Which targeting options you can use depends on your plan.'],
            ]],
        ],
    ],

    'ab-testing' => [
        'title' => 'A/B testing',
        'summary' => 'Set up, run and read A/B and A/B/C tests, and the statistics behind a winner.',
        'icon' => 'split',
        'h1' => 'Run A/B tests *you can trust.*',
        'lead' => 'Everything about A/B and A/B/C testing in OrderOrbit Space: what you can test, each setup step, how visitors are split, how to read results and when a winner is real.',
        'meta' => 'Available on the Growth and Scale plans · In the app: **A/B tests** in the left menu',
        'sections' => [
            'overview' => [
                'Overview',
                [
                    [
                        'p',
                        'An A/B test shows different versions of one live widget to different visitors at the same time, then compares what those visitors did. Because both groups shop in the same week, with the same traffic and the same prices, the difference between them comes from the change you made, not from the season or a marketing push.',
                    ],
                    [
                        'p',
                        'In OrderOrbit Space a test has a **control (A)**, which is your widget exactly as published, and one or two **variants (B, and optionally C)**. Each visitor is placed in one of them and always sees the same one. When the test has enough days, visitors and conversions, the app tells you whether a variant really did better, and you can apply it to everyone in one click.',
                    ],
                    [
                        'list',
                        ['Choose a widget', 'Create variants', 'Split traffic', 'Collect data', 'Read results', 'Apply the winner'],
                    ],
                ],
            ],
            'what' => [
                'What you can test',
                [
                    ['p', 'Tests run on **published** widgets. A variant can change:'],
                    [
                        'list',
                        [
                            '**Template**: a different layout from the same feature (for example cards instead of a slider).',
                            '**Text**: headlines, messages and button labels.',
                            '**Design**: colours, corners, borders, spacing and the other Design settings. Checkout blocks use Shopify\'s checkout styles (background, border, corners, width and text tone).',
                            '**Holdout**: hide the widget from that group, to measure what the widget is worth overall.',
                        ],
                    ],
                    [
                        'p',
                        'Products, prices, discounts and thresholds always stay as published. That keeps checkout honest: every shopper gets the price and offer the widget promised, whichever variant they saw.',
                    ],
                    [
                        'table',
                        ['Widget', 'Can be tested', 'Notes'],
                        [
                            [
                                'Product and cart upsells, countdowns, sticky add to cart, trust badges, sales pop, pre-orders, shipping bar, free gifts and other storefront widgets',
                                'Yes',
                                'Holdout isn\'t offered for widgets that apply a discount, so nobody gets a discount for a widget they couldn\'t see.',
                            ],
                            [
                                'Checkout blocks (reviews, countdown, shipping progress, free gift, promotion, trust, image)',
                                'Yes',
                                'Audience: cart value and country. See [Checkout and Thank You blocks](#checkout).',
                            ],
                            ['Thank You and Order Status blocks', 'Yes', 'Judged by click-through rate by default, since the order is already placed.'],
                            ['Bundles and Progressive gifts', 'Not yet', 'Their prices are applied at checkout by Shopify functions.'],
                            ['Post-purchase offer', 'Not yet', 'The offer is chosen by the server for each order.'],
                            ['Customer account blocks', 'Not yet', ''],
                        ],
                    ],
                ],
            ],
            'before' => [
                'Before you start',
                [
                    [
                        'list',
                        [
                            '**Be on Growth or Scale.** You can build a test on any plan, but launching needs Growth or Scale.',
                            '**Publish the widget** you want to test, and make sure it shows on your store (its block is placed in the Theme Editor, or in Shopify\'s checkout editor for checkout blocks).',
                            '**Check that Analytics is connected** (Analytics in the app). Tests are measured with the OrderOrbit Space web pixel.',
                            '**Make sure there\'s enough traffic.** A winner needs at least 1,000 visitors and 100 conversions in every variant. See the [sample size table](#practices) to estimate how long that takes for your store.',
                            '**Write down one idea to test**, and why you think it will help. Testing one change at a time makes the result easy to act on.',
                        ],
                    ],
                ],
            ],
            'setup' => [
                'Set up a test, step by step',
                [
                    [
                        'p',
                        'Open **A/B tests** in the app, choose the widget under **Create a test** and click **Create test**. You can also click **Create A/B test** on any published widget\'s page. The setup page has nine steps; you can save a draft at any point.',
                    ],
                    ['h3', '1. Widget and hypothesis'],
                    [
                        'p',
                        'Give the test a name you\'ll recognise later, such as "Upsell: slider vs cards". The hypothesis is optional but useful: what you\'re changing, what you expect to happen and why. For example: "Showing the upsell as a slider will raise add-to-cart rate because more products fit on mobile."',
                    ],
                    ['h3', '2. Variants'],
                    ['p', '**A · Control** is the widget exactly as published; it can\'t be edited here. **Variant B** starts as a copy of the control. Change one or more of:'],
                    [
                        'list',
                        [
                            '**Template**: pick another layout of the same feature.',
                            '**Text**: open the Text panel and rewrite headlines, messages or buttons. Fields you leave as they are keep the control\'s text.',
                            '**Design**: open the Design panel and change colours, corners, spacing and so on.',
                            '**Holdout**: tick it to hide the widget from this group instead.',
                        ],
                    ],
                    ['p', 'Tick **Add a third variant** for an A/B/C test. Variants must differ from the control; the app won\'t launch a test where B is identical to A.'],
                    ['h3', '3. Traffic allocation'],
                    [
                        'p',
                        'Choose what share of the test\'s visitors sees each variant. The shares must add up to **100%**; **Split evenly** fills them for you. An even split (50/50 or 34/33/33) reaches a result fastest. Give a risky variant less traffic (for example 80/20) if you want to limit its exposure, knowing the test will take longer.',
                    ],
                    ['h3', '4. Audience'],
                    [
                        'p',
                        'Choose who takes part. Visitors outside the audience see the widget as published and aren\'t counted. Leave everything empty to include everyone who sees the widget.',
                    ],
                    [
                        'table',
                        ['Setting', 'Storefront', 'Checkout and Thank You'],
                        [
                            ['Device (mobile or desktop)', 'Yes', 'No'],
                            ['Countries (two-letter codes, e.g. US, CA)', 'Yes', 'Yes'],
                            ['Products and collections', 'Yes', 'No'],
                            ['Cart value at least / at most', 'Yes', 'Yes'],
                            ['UTM source and campaign', 'Yes', 'No'],
                            ['New or returning shoppers', 'Yes', 'No'],
                        ],
                    ],
                    ['h3', '5. Primary metric'],
                    ['p', 'The one number that decides the winner. Pick it before you launch and don\'t change it afterwards.'],
                    [
                        'table',
                        ['Metric', 'What it measures', 'Best for'],
                        [
                            ['**Conversion rate**', 'Share of test visitors who placed an order after first seeing the widget.', 'Most tests: trust, countdowns, sticky add to cart, checkout blocks.'],
                            ['**Revenue per visitor**', 'Order revenue divided by visitors, so larger orders count.', 'Upsells and offers that change order size as much as conversion.'],
                            ['**Revenue**', 'Total revenue, compared per visitor so an uneven split stays fair.', 'When revenue is the goal; it\'s tested the same way as revenue per visitor.'],
                            [
                                '**Click-through rate**',
                                'Share of test visitors who clicked the widget (a button, link or accepting its offer).',
                                'Thank You and Order Status blocks, where the order is already placed.',
                            ],
                        ],
                    ],
                    ['h3', '6. Secondary metrics'],
                    [
                        'p',
                        'Extra numbers shown alongside the result to explain it. They don\'t decide the winner. Choose from add-to-cart rate, checkout-started rate, purchase rate, average order value, units per order, upsell acceptance, bundle completion and click-through rate.',
                    ],
                    ['h3', '7. Guardrails'],
                    [
                        'p',
                        'Guardrails protect against a "winner" that quietly hurts something else. A variant that breaks a guardrail is never declared the winner, even if its primary metric improves.',
                    ],
                    [
                        'list',
                        ['**Cart abandonment**: share of visitors who added to cart but didn\'t buy.', '**Negative interactions**: closes and declined offers per visitor.'],
                    ],
                    [
                        'p',
                        'The threshold is the largest increase you accept, in percentage points compared with the control. With a 5-point threshold, a control at 60% cart abandonment and a variant at 66% breaks the guardrail (+6 points).',
                    ],
                    ['h3', '8. Duration and sample'],
                    [
                        'p',
                        'The minimums every variant must reach before a winner can be named: at least **7 days**, **1,000 visitors** and **100 conversions** (or clicks, for click-through tests). You can raise them, not lower them. Seven days covers a full weekly cycle, since weekday and weekend shoppers often behave differently.',
                    ],
                    ['p', 'An **end date** is optional. If you set one, it must leave at least the minimum duration, and the test completes on its own on that date.'],
                    ['h3', '9. Preview and launch'],
                    [
                        'p',
                        'See every variant side by side as shoppers will see it (previews use your last saved setup). If anything blocks launching, it\'s listed here: allocation not adding up to 100%, an unpublished widget, another running test on the same widget, a variant identical to the control, or a plan without A/B testing. Click **Launch test**. The split starts on your store within a minute.',
                    ],
                ],
            ],
            'checkout' => [
                'Checkout and Thank You blocks',
                [
                    [
                        'p',
                        'Checkout, Thank You and Order Status blocks are tested inside Shopify\'s checkout by the OrderOrbit Space checkout extension, wherever the OrderOrbit Space block for that type is placed in the checkout editor.',
                    ],
                    [
                        'list',
                        [
                            '**Audience**: checkout only knows the cart value and the buyer\'s country, so those are the audience options.',
                            '**Metrics**: checkout blocks usually use conversion rate (did the buyer complete the order). Thank You and Order Status blocks default to click-through rate, because the order is already placed; their "purchase rate" counts later orders.',
                            '**Blocks inside checkout** need Shopify Plus (development stores can preview them). Thank You and Order Status blocks work on every Shopify plan.',
                            'While the extension loads the buyer\'s assignment, the block waits a moment instead of flashing one version and then another.',
                        ],
                    ],
                ],
            ],
            'assignment' => [
                'How visitors are split',
                [
                    [
                        'list',
                        [
                            '**Stable assignment.** Each visitor gets a random id stored in their browser (in checkout, in the extension\'s storage). The variant comes from a hash of the test and that id, so the same visitor always lands in the same variant, on every page and every visit.',
                            '**Audience first.** Visitors outside the audience see the widget as published and aren\'t counted in the results.',
                            '**Exposure.** A visitor joins the test the first time they see the widget; that\'s recorded once. They belong to that variant from then on.',
                            '**What counts.** Orders, adds to cart, checkouts and clicks after a visitor\'s first exposure, until the test ends. Orders placed before the visitor saw the test never count.',
                            '**Holdout.** Visitors in a holdout group are counted as exposed, but the widget isn\'t shown to them.',
                            '**Consent.** Results only include shoppers who allow analytics in your cookie banner. The split itself still applies to everyone.',
                        ],
                    ],
                ],
            ],
            'results' => [
                'Reading the results',
                [
                    ['p', 'Open a running or finished test from **A/B tests**. The banner at the top tells you where the test stands:'],
                    [
                        'list',
                        [
                            '**Collecting data**: The test hasn\'t reached its minimum days, visitors or conversions in every variant yet. It shows what\'s missing and a progress bar. No winner is shown, however good a variant looks.',
                            '**Winner declared**: A variant is significantly better than the control on the primary metric, all minimums are met and no guardrail is broken. You can apply it.',
                            '**The control wins**: Every variant did significantly worse than the control. Keep the widget as it is.',
                            '**No clear winner**: The minimums are met but the difference isn\'t statistically significant. The change probably doesn\'t matter much; keep whichever you prefer, or test a bolder idea.',
                            '**Guardrail breached**: A variant improved the primary metric but broke a guardrail, so it isn\'t declared the winner.',
                        ],
                    ],
                    ['h3', 'The variants table'],
                    [
                        'table',
                        ['Column', 'Meaning'],
                        [
                            ['Traffic', 'The share of the test\'s traffic set for the variant.'],
                            ['Visitors', 'Visitors exposed to the variant (the sample size).'],
                            ['Conversions', 'Visitors who placed at least one order after exposure.'],
                            ['Conversion rate', 'Conversions ÷ visitors.'],
                            ['Revenue', 'Total value of those visitors\' orders after exposure.'],
                            ['Revenue / visitor', 'Revenue ÷ visitors.'],
                            ['AOV', 'Revenue ÷ orders.'],
                            [
                                'Lift vs control',
                                'How much better or worse the variant is than the control on the primary metric, in percent, with its confidence interval in brackets. An interval that includes 0 means the difference could be chance.',
                            ],
                            [
                                'p-value',
                                'The probability of seeing a difference at least this big if the variants were really the same. Smaller is stronger evidence; it must be below the significance level (0.05, or 0.025 per comparison in an A/B/C test).',
                            ],
                        ],
                    ],
                    ['h3', 'The rest of the page'],
                    [
                        'list',
                        [
                            '**Conversion rate over time**: cumulative conversion per variant. Early lines jump around; they settle as visitors accumulate.',
                            '**Secondary metrics** and **Guardrails**: each variant\'s value, with OK or Breached for guardrails.',
                            '**By device**: visitors and conversion per variant on mobile and desktop.',
                            '**Statistical method** and **History**: how the result is calculated, and every launch, pause, resume and stop.',
                            '**Export CSV**: the variants table as a spreadsheet.',
                        ],
                    ],
                    [
                        'p',
                        'Test results also appear in **Analytics → Revenue & attribution** (revenue per variant) and in **Event Explorer** (break any event down by A/B test or variant).',
                    ],
                ],
            ],
            'method' => [
                'The statistical method',
                [
                    ['p', 'OrderOrbit Space uses a fixed-horizon, frequentist test, the standard approach for e-commerce experiments:'],
                    [
                        'table',
                        ['Metric', 'Test'],
                        [
                            ['Conversion rate, click-through rate', 'Two-proportion z-test (pooled standard error for the p-value, unpooled for the interval)'],
                            ['Revenue per visitor, revenue, AOV', 'Welch\'s t-test (doesn\'t assume equal variances; Welch–Satterthwaite degrees of freedom)'],
                        ],
                    ],
                    [
                        'list',
                        [
                            '**Confidence**: 95%. In an A/B/C test, each variant is compared with the control, so the significance level is split between the two comparisons (Bonferroni correction: 0.05 ÷ 2 = 0.025 each). That keeps the chance of a false winner at 5% overall.',
                            '**Winner rule**: all of these must be true: (1) the test has run at least the minimum days (7 or more); (2) every variant has at least the minimum visitors (1,000 or more) and conversions or clicks (100 or more); (3) the variant is better than the control on the primary metric, with p below the significance level; (4) the variant hasn\'t broken any guardrail.',
                            'If several variants qualify, the one with the largest lift wins.',
                        ],
                    ],
                    [
                        'code',
                        'Conversion:  z = (p_B − p_A) / √( p(1 − p) · (1/n_A + 1/n_B) ),  p = pooled conversion rate
Revenue:     t = (mean_B − mean_A) / √( s_A²/n_A + s_B²/n_B )
Lift:        (B − A) / A,  shown with its confidence interval',
                    ],
                    [
                        'note',
                        'Attribution and tests measure what happened to the visitors in each group. With randomised groups, a significant difference is good evidence the change caused it, but no test is certain: at 95% confidence, about 1 in 20 tests of a change that does nothing will still look significant.',
                    ],
                ],
            ],
            'manage' => [
                'Pause, stop and apply a winner',
                [
                    [
                        'table',
                        ['Action', 'What happens'],
                        [
                            ['**Pause**', 'Everyone sees the control until you resume. Visitors keep their variant. While paused you can edit the setup.'],
                            ['**Resume**', 'The split starts again with the same assignments.'],
                            ['**Stop test**', 'Ends the test early. Everyone sees the widget as published, and the result at that moment is saved.'],
                            ['**Apply winner**', 'Publishes the winning variant\'s template, text and design to the widget for everyone, and completes the test.'],
                            ['**End date**', 'A test with an end date completes on its own that day.'],
                            ['**Duplicate as new test**', 'Copies the setup into a new draft, for a follow-up test.'],
                            ['**Delete**', 'Removes a draft or a finished test. Running tests must be stopped first.'],
                        ],
                    ],
                    ['p', 'Only one test can run on a widget at a time. Editing the widget itself while a test runs changes what every variant inherits, so avoid it until the test ends.'],
                ],
            ],
            'practices' => [
                'Best practices',
                [
                    [
                        'list',
                        [
                            '**Test one idea at a time.** If B changes the layout and the headline and the colours, a win won\'t tell you which change mattered.',
                            '**Make the change big enough to matter.** Tiny tweaks need huge samples to detect. Bold, meaningful changes reach a result sooner.',
                            '**Run full weeks.** Let tests run in whole weeks (7, 14, 21 days) so every weekday is represented equally.',
                            '**Don\'t stop early because it looks good.** Early results swing a lot. The app won\'t name a winner before the minimums, and neither should you.',
                            '**Avoid big events mid-test.** A flash sale or a viral post changes who visits. If one happens, consider extending the test.',
                            '**Keep a record.** Write the hypothesis, and after the test, what you learned. "No clear winner" is a useful result too.',
                        ],
                    ],
                    ['h3', 'How many visitors do I need?'],
                    ['p', 'Visitors needed **per variant** to detect a relative improvement in conversion rate, at 95% confidence and 80% power:'],
                    [
                        'table',
                        ['Current conversion rate', 'Detect +10%', 'Detect +20%', 'Detect +30%'],
                        [
                            ['1%', '163,100', '42,700', '19,900'],
                            ['2%', '80,700', '21,200', '9,800'],
                            ['3%', '53,300', '14,000', '6,500'],
                            ['5%', '31,300', '8,200', '3,800'],
                        ],
                    ],
                    [
                        'p',
                        'Example: a store converting at 2% that wants to detect a 20% lift (2% → 2.4%) needs about 21,200 visitors per variant, so about 42,400 for an A/B test. With 3,000 visitors a day seeing the widget, that\'s about two weeks. Find your conversion rate in Analytics. Click-through tests use the same table with click-through rate.',
                    ],
                ],
            ],
            'limits' => [
                'Limits',
                [
                    [
                        'list',
                        [
                            'Bundles, Progressive gifts, the post-purchase offer and customer account blocks can\'t be tested yet.',
                            'Variants can\'t change products, prices, discounts or thresholds.',
                            'Refund rate isn\'t available as a guardrail: refunds can\'t be reliably matched to test visitors.',
                            'A visitor on a new device or browser gets a new id, so they may see a different variant there.',
                            'Results only include shoppers who allow analytics.',
                            'Customer segments as a test audience arrive with Audiences & Personalization.',
                        ],
                    ],
                ],
            ],
            'faq' => [
                'Troubleshooting and FAQ',
                [
                    [
                        'faq',
                        [
                            [
                                'The test shows 0 visitors.',
                                'Check that the widget shows on your store (its block is placed, and its page and targeting match), that Analytics is connected, and that you browse with analytics cookies allowed. Visitors appear within a few minutes of seeing the widget.',
                            ],
                            [
                                'I always see the same variant. How do I check the others?',
                                'That\'s stable assignment working. Use the previews on the setup page, or open your store in a private window: each new private window is a new visitor.',
                            ],
                            [
                                'Why is there no winner even though B looks better?',
                                'Either the minimums aren\'t met yet (the banner lists what\'s missing) or the difference isn\'t statistically significant. Small differences often disappear with more data.',
                            ],
                            [
                                'Can I change the test while it runs?',
                                'Pause it first; then you can edit the setup and resume. Changing variants mid-test mixes two experiments, so prefer stopping and duplicating for a new idea.',
                            ],
                            ['What happens to visitors when the test ends?', 'Everyone sees the widget as published, or the winner if you applied it.'],
                            ['Does testing slow down my store?', 'No. The test script is under 1 KB and only loads on pages with a widget under test.'],
                            ['Is the visitor id personal data?', 'It\'s a random id with no name, email or address, stored in the shopper\'s browser to keep their variant stable.'],
                            ['Which plan do I need?', 'Growth or Scale to launch tests. You can set tests up on any plan.'],
                        ],
                    ],
                ],
            ],
        ],
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
