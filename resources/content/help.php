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
            ['How do I install Growvia?', 'Open the listing in the Shopify App Store and click Install. Approve the permissions, choose a plan and you land on the app dashboard inside your Shopify admin.'],
            ['What should I set up first?', 'Start with one bundle on your best-selling product and a progressive gifts bar with free shipping at your current free-shipping threshold. Together they usually give the quickest lift in order value.'],
            ['Do I need to enable the app in my theme?', 'Yes, once. In your Shopify admin go to Online Store → Themes → Customize → App embeds and turn on Growvia. The dashboard shows a reminder until it is on.'],
        ],
    ],
    [
        'name' => 'Theme setup',
        'icon' => 'layers',
        'text' => 'Where offers appear and how to place blocks in your theme.',
        'articles' => [
            ['Where do bundles and gift bars appear?', 'Bundles and progressive gifts show on the product page next to your add-to-cart button automatically. You choose above or below the button in the offer settings.'],
            ['How do I place a countdown, trust block or upsell?', 'Open the Theme Editor, go to the page you want, click Add block and choose the Growvia block. Drag it where you want it and save.'],
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
            ['How do pre-orders work?', 'Pick the products and the ship date, then add the Growvia block to your product template and choose "Pre-order". Turn on "Continue selling when out of stock" for those products in Shopify. Each pre-order item gets a "Pre-order: Ships by …" line on the order.'],
            ['Where does Sales pop get its purchases?', 'From your store\'s real recent orders: the product, the order\'s country and the time. No names are shown and nothing is invented. It shows on every page while the app embed is on.'],
            ['When does the sticky add-to-cart show?', 'After your theme\'s own buy button scrolls out of view, on the devices you choose. It disappears again when the button is back on screen.'],
        ],
    ],
    [
        'name' => 'Analytics',
        'icon' => 'chart',
        'text' => 'What is measured, funnels, attribution and customer journeys.',
        'articles' => [
            ['How is revenue from offers measured?', 'A Shopify web pixel records completed orders. Each order line that was added by a bundle, gift or upsell is credited to that offer.'],
            ['Why do my numbers differ from Shopify reports?', 'Analytics only count shoppers who allow analytics in your consent banner, and they start from the day the app was installed.'],
            ['How long is data kept?', 'Analytics events are kept for 13 months and then deleted automatically.'],
            ['How do funnels count shoppers?', 'A shopper counts for a step when they did it after the previous step, within the funnel\'s window: the same session, or 1, 7 or 30 days from the first step. Start from a ready-made funnel or pick up to 8 events, optionally for one bundle, gift or upsell.'],
            ['What are first touch, last touch and assisted?', 'Last touch credits the traffic source of the visit the order was placed in; first touch credits the shopper\'s first visit within the attribution window. Assisted counts orders from shoppers who saw or used an offer in that window; each offer gets the whole order, so assisted totals overlap. Attribution is a model, not proof that something caused a sale.'],
            ['Where does the traffic source come from?', 'From UTM tags on the landing page (utm_source, utm_medium, utm_campaign), then Google and Facebook click ids, then the referring site. Visits with none of these are "direct".'],
            ['What does a customer journey show?', 'One shopper\'s visits in order: pages and products viewed, offers seen and used, cart and checkout steps, purchases (with repeat purchases marked) and the automations that ran for them. Shoppers are an anonymous visitor id, or a customer number once they sign in or buy; no names or emails are stored.'],
        ],
    ],
    [
        'name' => 'Customer accounts',
        'icon' => 'user',
        'text' => 'Orders, tracking, reorder, rewards, reviews, products and support in customer accounts.',
        'articles' => [
            ['How do I add a customer account block?', 'Create the block in Growvia and publish it. In Shopify, open Settings → Checkout → Customize, switch to the Orders, Profile or Order status page, add the Growvia account block and choose its type. Customers see it the next time they sign in.'],
            ['Which stores can use them?', 'Stores on Shopify\'s new customer accounts (Settings → Customer accounts). Classic accounts can\'t show app blocks. Which plans include them is shown on the Pricing page.'],
            ['How does "Buy again" work?', 'It adds the order\'s items to the cart on your store, with all items or the ones the customer picks. With the Reorder block published, "Buy again" also appears in each order\'s menu.'],
            ['How are reward tiers worked out?', 'From the customer\'s total spend across their recent orders (cancelled orders don\'t count). You set the tiers, thresholds and perks.'],
            ['Where does "Write a review" go?', 'To the review link you set, such as {product_url}#reviews or your review app\'s page, with the product filled in.'],
        ],
    ],
    [
        'name' => 'Audiences & personalization',
        'icon' => 'target',
        'text' => 'Segments, personalization rules and where segments can be used.',
        'articles' => [
            ['How do I create a segment?', 'Open Audiences → Segments and add a ready-made segment (new, returning, VIP, high-AOV, at-risk, lapsed and more) or a custom one. Combine rules for orders, total spent, average order, days since last order, tags, products bought, market, device and widgets used, with all or any matching.'],
            ['How are segments worked out?', 'On your store, during the visit, from the signed-in customer\'s own account (guests have 0 orders) and the visit itself. "Used a widget" is remembered on the shopper\'s device. Nothing about the shopper is sent to Growvia.'],
            ['What do personalization rules do?', 'A rule picks a widget and an audience (segments plus optional device, cart value and UTM conditions), then shows the widget only to them, shows it in another template, or hides it. Rules run top to bottom; for each widget the first match wins.'],
            ['Where else can I use segments?', 'In a widget\'s Targeting step (Only these segments), as an A/B test audience, and in workflow conditions (Customer segment), which check the order\'s customer.'],
            ['Why is there no member count?', 'Shopify can only count segments made of customer fields (orders, total spent, tags). Segments with browsing or purchase rules are only known during a visit.'],
        ],
    ],
    [
        'name' => 'A/B testing',
        'icon' => 'split',
        'text' => 'Split tests on live widgets, metrics and how winners are decided. Full guide: growvia.orderorbit.space/docs/ab-testing.',
        'articles' => [
            ['How do I run a test?', 'Open A/B tests, choose a published widget and create a test. Change variant B (template, text or design, or hide it as a holdout), set the traffic split, audience, metrics and guardrails, preview, then launch.'],
            ['How are visitors split?', 'Each visitor is put in a variant by a stable hash of the test and a visitor id kept in their browser, so they see the same version every visit. Visitors outside the audience see the widget as published and aren\'t counted.'],
            ['When is a winner declared?', 'Only after the test has run at least 7 days and every variant has 1,000 visitors and 100 conversions, and the primary metric is significantly better at 95% confidence (Bonferroni-corrected for A/B/C) without breaking a guardrail. Until then the results say "Collecting data".'],
            ['What counts as a conversion?', 'A visitor places an order after first seeing the test. Revenue per visitor and AOV use those orders. Only shoppers who allow analytics are counted.'],
            ['Can I test checkout and Thank You blocks?', 'Yes. The split happens inside Shopify\'s checkout, wherever the Growvia block for that type is placed. Checkout tests can target cart value and country. Thank You and Order Status blocks are judged by click-through rate, since the order is already placed.'],
            ['What does "Apply winner" do?', 'It publishes the winning variant\'s template, text and design to the widget for everyone and completes the test.'],
        ],
    ],
    [
        'name' => 'Automation',
        'icon' => 'flow',
        'text' => 'Workflows that follow up after orders, deliveries, refunds and new customers.',
        'articles' => [
            ['How do I create a workflow?', 'In the app go to Automation → Templates and pick one, or start blank. Choose the trigger, add conditions, waits and actions on the canvas, then click Test with a sample order. When the run looks right, click Publish.'],
            ['Does a test change my store?', 'No. A test walks through a sample order, skips waits and shows what each step would do. No tags, codes, tasks, emails or webhooks are created.'],
            ['Are emails sent?', 'Not yet. Email steps prepare each message with the order\'s details and keep it under Automation → Emails. Sending through an email provider is being connected now; tags, discount codes, tasks and webhooks work today.'],
            ['What happens when a step fails?', 'It is retried after 5 minutes and again after 30. Steps that already finished are never repeated. If it still fails, the run is marked failed and the log shows why.'],
            ['Can the same order start a workflow twice?', 'No. Each Shopify event starts each workflow once, even if Shopify sends the event again.'],
            ['Which plan includes automation?', 'Every plan includes workflows, with a number of active workflows and runs a month that grows with the plan. If/else branching and webhooks are on higher plans; the Pricing page compares them.'],
        ],
    ],
    [
        'name' => 'Billing and plans',
        'icon' => 'card',
        'text' => 'Plans, limits, upgrades and cancelling.',
        'articles' => [
            ['How am I billed?', 'Through your Shopify invoice. There is no separate card to add.'],
            ['How do the plans work?', 'Start free, then upgrade when you need more widgets, testing, automation and personalization. Plans differ by the features they include and by their limits (live offers, workflows, automation runs a month). The Pricing page and Settings → Billing in the app compare them.'],
            ['What happens when I reach a limit?', 'The app warns you as you get close. At the limit, everything already live keeps working; you just can\'t add more until you upgrade. Nothing is deleted.'],
            ['Can I change plans?', 'Yes, at any time from Plans in the app. Upgrades take effect straight away.'],
            ['What happens if I uninstall?', 'Billing stops, offers are removed from your store and checkout pricing stops. Your store data is deleted as Shopify requires.'],
        ],
    ],
    [
        'name' => 'Developers: callbacks',
        'icon' => 'tool',
        'text' => 'Run your own theme code when shoppers use a Growvia offer.',
        'articles' => [
            ['What are callbacks?', 'Callbacks let your theme run its own JavaScript at key moments, such as right after a Growvia button adds to the cart. You write the code in your theme (for example in theme.liquid or a theme script file); nothing is stored in the app. Define functions on window.GrowviaHooks, or listen for the matching events on document. Both work, and you can use either.', <<<'JS'
            <script>
              window.GrowviaHooks = {
                beforeAddToCart: function (detail) { /* runs before items are sent */ },
                afterAddToCart:  function (detail) { /* runs after a successful add */ },
                addToCartFailed: function (detail) { /* runs when Shopify refuses the add */ }
              };
            </script>
            JS],
            ['How do I open my cart drawer after an add?', 'Use afterAddToCart and return false. Returning false tells Growvia you have handled it, so the shopper stays on the page instead of being sent to the cart. Then open your drawer the way your theme does. The example is for Dawn and themes based on it; other themes have their own way to open the drawer.', <<<'JS'
            window.GrowviaHooks = {
              afterAddToCart: async function (detail) {
                // Ask Shopify for the drawer's new HTML and let the theme redraw it.
                const drawer = document.querySelector('cart-drawer');
                if (!drawer) return;               // no drawer: keep the default (go to cart)

                const ids = drawer.getSectionsToRender().map((s) => s.id);
                const res = await fetch('/?sections=' + ids.join(','));
                drawer.renderContents({ sections: await res.json() });
                return false;                      // stay on the page
              }
            };
            JS],
            ['What is in "detail"?', 'Every callback receives one object. detail.experience tells you which offer was used: its id, its type (bundles, cart-upsells, progressive-gifts and so on) and its template. detail.items is the list being added, each with a variant id, a quantity and line properties. detail.after is what Growvia will do next: "cart", "checkout" or "stay". detail.element is the widget on the page. After a successful add, detail.response is Shopify\'s reply. detail.getCart() returns the live cart.', <<<'JS'
            window.GrowviaHooks = {
              afterAddToCart: async function (detail) {
                console.log(detail.experience.type);   // e.g. "bundles"
                console.log(detail.items);             // [{ id, quantity, properties }]
                console.log(detail.response);          // Shopify's /cart/add.js reply
                const cart = await detail.getCart();   // Shopify's /cart.js
                console.log(cart.item_count, cart.total_price);
              }
            };
            JS],
            ['Can I change or cancel an add?', 'Yes, in beforeAddToCart. Change detail.items to alter what is added, for example to attach a line property. Return false to cancel the add completely. Keep the _oo_offer property that is already on each item: it is how the offer\'s price is applied at checkout.', <<<'JS'
            window.GrowviaHooks = {
              beforeAddToCart: function (detail) {
                if (!document.querySelector('#terms').checked) {
                  alert('Please accept the terms first.');
                  return false;                         // cancel the add
                }
                detail.items.forEach(function (item) {
                  item.properties['Gift wrap'] = 'Yes'; // add a line property
                });
              }
            };
            JS],
            ['Can I send shoppers somewhere else after an add?', 'Yes. Set detail.after in afterAddToCart to "cart", "checkout" or "stay" to override the offer\'s own setting, or return false and redirect yourself.', <<<'JS'
            window.GrowviaHooks = {
              afterAddToCart: function (detail) {
                if (detail.experience.type === 'bundles') detail.after = 'checkout';
              }
            };
            JS],
            ['Can I use events instead of functions?', 'Yes. The same three moments are sent as events on document: growvia:before-add, growvia:added-to-cart and growvia:add-failed. The event\'s detail is the same object, and calling preventDefault() does what returning false does. Events can\'t wait for asynchronous work, so use the functions when you need await. There is also growvia:event, which reports views, clicks and unlocked rewards for your own tracking.', <<<'JS'
            document.addEventListener('growvia:added-to-cart', function (event) {
              event.preventDefault();                  // stay on the page
              myTheme.openCart();
            });

            document.addEventListener('growvia:event', function (event) {
              // event.detail.event is e.g. "growvia:experience_viewed"
              console.log(event.detail.event, event.detail.experience_id);
            });
            JS],
            ['Which buttons call the callbacks?', 'Every add-to-cart button that Growvia draws: bundles, quantity breaks, cart upsells, gifts and add-ons. Sticky add to cart and Pre-order use your theme\'s own button, so your theme\'s normal behaviour applies there. If your callback has an error, the add still goes through and the error is written to the browser console.'],
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
