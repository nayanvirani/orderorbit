<?php

/*
|--------------------------------------------------------------------------
| Long-form feature copy for the public site
|--------------------------------------------------------------------------
|
| Merged into resources/content/features.php by App\Support\Content.
| status: live (in the app today) or soon (planned; shown as "Coming soon").
| summary: one sentence for lists. overview: short paragraphs.
| benefits: [title, text] pairs.
|
*/

return [

    'bundles' => [
        'status' => 'live',
        'summary' => 'Quantity breaks, mix & match, fixed packs and gift bundles that check out as one line.',
        'overview' => [
            'Bundles are the fastest way to raise order value, as long as shoppers understand the offer in a second and it works with your theme. OrderOrbit Space gives you six bundle types — quantity breaks, quantity breaks with gifts, variant offers, mix & match, fixed bundles and fixed bundles with gifts — each with ready-made layouts you can restyle to match your store.',
            'Shoppers pick their offer, choose a variant for each item and add everything with one click. In the cart the bundle appears as a single line at the bundle price, and your orders still list every product, so Shopify deducts stock from each one. Where a bundle shows, it replaces your theme\'s own variant picker and add-to-cart so nothing conflicts.',
        ],
        'benefits' => [
            ['One bundle line in the cart', 'The whole offer checks out as one line at the bundle price. No discount codes to remember.'],
            ['Real inventory', 'Orders keep each product, so stock is deducted item by item and fulfilment works as usual.'],
            ['Variant choice per item', 'Map which variants each product offers; shoppers pick a size or colour for every unit.'],
            ['Gifts and add-ons inside the bundle', 'Attach free gifts to an offer or add discounted upsells with a checkbox.'],
        ],
    ],

    'progressive-gifts' => [
        'status' => 'live',
        'summary' => 'Free gifts, free shipping and discounts that unlock as the cart grows.',
        'overview' => [
            'Shoppers add one more item when they can see exactly what it unlocks. Progressive gifts puts every reward on one track: a free gift at $50, free shipping at $75, a choice of gifts at $100 — or the same by number of items.',
            'Rewards apply automatically at checkout. Gifts can be added for the shopper or claimed with a tap, and if the cart drops below a threshold the gift is taken out, so nobody pays for a "free" item. Pick one of five layouts and style it to match your store.',
        ],
        'benefits' => [
            ['One bar for every reward', 'Free shipping, gifts and order discounts on a single progress track.'],
            ['By value or by items', 'Unlock rewards by cart total or by how many items are in the cart.'],
            ['Applied at checkout', 'No codes. Rewards apply automatically while the cart qualifies.'],
            ['Five layouts', 'Classic bar, minimal strip, milestone steps, reward cards or radial counter.'],
        ],
    ],

    'cart-upsells' => [
        'status' => 'live',
        'summary' => 'A last add-on in the cart, with an optional incentive.',
        'overview' => [
            'The cart is where shoppers decide. A short, relevant recommendation there — a case for the phone, a refill for the serum — adds order value without interrupting the path to checkout.',
            'Cart upsells hide products already in the cart, let shoppers pick a variant and add with one tap. If you offer an incentive, it applies only to items added from the recommendation, automatically at checkout.',
        ],
        'benefits' => [
            ['One-tap add', 'Shoppers add the recommendation without leaving the cart.'],
            ['Incentives that stay fair', 'The discount applies only to items added from the offer.'],
            ['Four layouts', 'Carousel, grid, horizontal or minimal card.'],
            ['Never pushy', 'No pop-ups and no forced cart drawers.'],
        ],
    ],

    'countdown-timer' => [
        'status' => 'live',
        'summary' => 'Countdowns for real deadlines, in eight layouts.',
        'overview' => [
            'A countdown works when the deadline is real. OrderOrbit Space counts down to your campaign\'s end date, or to a daily cutoff such as "order within 3h 12m to ship today", in your store\'s time zone. Timers never reset per visitor.',
            'Choose from eight layouts — from a single line of text to a flip clock, progress rings or a banner with a button — show a discount code shoppers can copy, and switch to an urgent colour in the final hours.',
        ],
        'benefits' => [
            ['Honest urgency', 'Real end dates and daily cutoffs only; timers never reset per visitor.'],
            ['Eight layouts', 'Minimal, banner, premium card, offer, product, flip clock, circles and shipping cutoff.'],
            ['Code and button', 'Show a discount code with one-tap copy and a button to your sale.'],
            ['Knows when to stop', 'Hide the timer or show a message when the campaign ends.'],
        ],
    ],

    'sticky-add-to-cart' => [
        'status' => 'live',
        'summary' => 'Keeps the buy button in reach on long product pages.',
        'overview' => [
            'On long product pages the buy button scrolls out of sight just as shoppers make up their minds. A slim sticky bar appears once your theme\'s own button leaves the screen and disappears when it comes back.',
            'A tap scrolls shoppers back to your product form so they can pick options, or uses your theme\'s own add-to-cart. Your theme keeps its variant, quantity, subscription and cart-drawer behaviour.',
        ],
        'benefits' => [
            ['Appears only when needed', 'Shows after the theme\'s buy button scrolls away.'],
            ['Works with your theme', 'Uses your product form and cart drawer; no duplicate cart logic.'],
            ['Three layouts', 'Simple bar, floating pill or full-width bar with compare-at price.'],
            ['Device control', 'Show it on mobile, desktop or both, at the offset you choose.'],
        ],
    ],

    'preorder' => [
        'status' => 'live',
        'summary' => 'Take orders before stock arrives, with a ship date, progress bar and countdown.',
        'overview' => [
            'A pre-order turns "out of stock" into a sale. OrderOrbit Space adds a pre-order widget to the products you choose, showing when the item ships, how close it is and how long is left — in months, weeks or days.',
            'Your theme\'s add-to-cart reads "Pre-order now", and each item gets a "Pre-order: Ships by …" line so you and your customer always know what\'s coming. Show it on every variant, or only when the selected variant is sold out.',
        ],
        'benefits' => [
            ['Only on the products you pick', 'Choose the products, and optionally show it only when a variant is out of stock.'],
            ['Ship date and countdown', 'A fixed date or a number of days after the order, with time left in months, weeks or days.'],
            ['Progress bars', 'Show time until shipping or units reserved toward a goal.'],
            ['Eight layouts', 'Classic card, minimal line, timeline steps, countdown tiles, goal tracker, premium dark, banner and badge pill.'],
        ],
    ],

    'sales-pop' => [
        'status' => 'live',
        'summary' => 'Recent-purchase notifications on every page, from your real orders.',
        'overview' => [
            'Seeing that others are buying reassures new shoppers. Sales pop shows small notifications such as "Someone in Canada purchased Glow Serum · 12 minutes ago" — built only from your store\'s real recent orders, never invented.',
            'It runs on every page through the app embed, so there\'s no block to place. Choose the corner on desktop and mobile, how long each pop stays, the gap between them and whether they come in random or newest-first order.',
        ],
        'benefits' => [
            ['Real orders only', 'Product, country and time from actual orders; no names and nothing made up.'],
            ['Every page, no block', 'Turn on the app embed once and it pops wherever you choose.'],
            ['Full control over timing', 'First pop, auto close, random gaps, pops per page, loop and pause on hover.'],
            ['Five layouts', 'Classic card, rounded pill, dark toast, minimal text and slim bar.'],
        ],
    ],

    'trust-social-proof' => [
        'status' => 'live',
        'summary' => 'Reviews, ratings, trust rows and guarantees that match your brand.',
        'overview' => [
            'Shoppers look for reassurance before they buy: what others say, how returns work, whether checkout is safe. Trust blocks put that information exactly where hesitation happens — next to the price, in the cart, on the home page.',
            'Add your rating, a few reviews, trust badges or a guarantee, and pick one of seven layouts. Everything uses your store\'s colours and fonts.',
        ],
        'benefits' => [
            ['Seven layouts', 'Review cards, slider, rating strip, quote, avatars, trust row and guarantee card.'],
            ['Your words', 'Add reviews, ratings and guarantees in minutes.'],
            ['Anywhere in your theme', 'Place it on product pages, the cart or the home page.'],
            ['On brand', 'Shares your colours and fonts with every other block.'],
        ],
    ],

    'checkout' => [
        'status' => 'live',
        'summary' => 'Blocks for checkout, Thank You and Order Status: reviews, trust, progress, cross-sells, reorders and more.',
        'overview' => [
            'Shoppers hesitate at checkout, and after they buy the confirmation page is usually a dead end. OrderOrbit Space adds blocks to both: reviews, a real countdown, shipping progress, free gifts, promotions and trust inside checkout, and cross-sells, reorder, review requests, referrals, surveys, next-order codes and helpful messages on the Thank You and Order Status pages.',
            'You place the blocks in Shopify\'s own checkout editor and they use your checkout branding. Shipping progress and free gifts follow your Progressive gifts campaign, so checkout and storefront always agree. Blocks inside checkout need Shopify Plus; Thank You and Order Status blocks work on every plan, and the app only offers what your store can use.',
        ],
        'benefits' => [
            ['15 block types', 'Seven for checkout and eight for Thank You and Order Status, including images, each with ready-made layouts.'],
            ['Your size and shape', 'Set each block\'s width, height, background, border, corners and text colour, using your checkout branding\'s colours.'],
            ['Post-purchase funnel', 'A one-click offer right after payment, added to the same order without re-entering card details, with a second offer if declined.'],
            ['Only what your store supports', 'In-checkout blocks appear only on Shopify Plus and development stores; everything else works on every plan.'],
            ['One source of truth', 'Shipping progress and free gifts read your live Progressive gifts campaign, and gifts can be claimed right in checkout.'],
            ['Survey answers in the app', 'Ask one question after checkout and see the answers on the survey\'s page.'],
        ],
    ],

    'customer-accounts' => [
        'status' => 'live',
        'summary' => 'My orders, tracking, reorder, rewards, reviews, products and support in customer accounts.',
        'overview' => [
            'Your best customers sign in to their account, and most stores show them an order list and nothing else. OrderOrbit Space adds seven blocks to Shopify\'s new customer accounts: a summary of their orders, live shipment tracking, one-tap reorder, reward tiers by total spend, review requests for what they bought, a shelf of their products with buy again, and your support options and answers.',
            'Each block reads the customer\'s own orders, so what they see is always theirs. You place the blocks in Shopify\'s customer accounts editor, give them your size, background, border and corners, and "Buy again" can also appear in every order\'s menu.',
        ],
        'benefits' => [
            ['One-tap reorder', 'Buy again from any order\'s menu or a block, with all items or the ones the customer picks.'],
            ['Where is my order?', 'Shipment status, carrier, tracking links and estimated delivery on the account and order pages.'],
            ['Rewards and reviews', 'Reward tiers with progress to the next perk, and review requests for the products they bought.'],
            ['Their products and your support', 'A shelf of purchased products with buy again, plus contact options and answers to common questions.'],
        ],
    ],

    'automation' => [
        'status' => 'live',
        'summary' => 'Workflows triggered by what customers do: tags, codes, tasks and follow-ups.',
        'overview' => [
            'The second order rarely happens by itself, and manual follow-ups get forgotten. Lifecycle automation reacts to real Shopify events, such as an order paid, delivered or refunded, a new customer or a tag, and follows up for you: tag a VIP, create a single-use code, remind your team, call your own systems, or prepare a review or win-back email.',
            'Build on a visual canvas with waits and if/else branches, or start from one of 10 templates. Test on a sample order before you publish; every run is logged, never starts twice for the same event and retries failed steps on its own. Email steps prepare each message today, and sending through an email provider is being connected now.',
        ],
        'benefits' => [
            ['10 templates', 'Review request, welcome, VIP, reorder, win-back, cross-sell and more, ready to publish.'],
            ['Reliable runs', 'Every run is logged, starts once per event and retries failed steps automatically.'],
            ['Safe to try', 'Test runs walk through a sample order and change nothing in your store.'],
            ['Versions', 'Every publish is saved, and any earlier version can be restored.'],
        ],
    ],

    'analytics' => [
        'status' => 'live',
        'summary' => 'Events, funnels, attribution and customer journeys, and how much came from your offers.',
        'overview' => [
            'OrderOrbit Space measures what matters with a Shopify web pixel that respects your customers\' consent choices. You see store revenue, orders, average order value and conversion rate, and exactly how much revenue came from lines your bundles, gifts and upsells added.',
            'Every offer has its own numbers — views, adds to cart, orders and revenue — so you can keep what works and change what doesn\'t.',
            'Go deeper with the Event Explorer, funnels with drop-off at every step, revenue by traffic source under first-touch, last-touch and widget-assisted models, and a journey for each customer from first visit to repeat purchase.',
        ],
        'benefits' => [
            ['Revenue from offers', 'Order lines are credited to the offer that added them.'],
            ['Store health', 'Revenue, orders, AOV and conversion rate, compared with the previous period.'],
            ['Per-offer results', 'Views, adds to cart, orders and revenue for each bundle, gift and upsell.'],
            ['Funnels and journeys', 'See where shoppers drop off, how long each step takes, and each customer\'s path to a repeat purchase.'],
            ['Consent-aware', 'Only shoppers who allow analytics are counted; no personal data is sent.'],
        ],
    ],

    'ab-testing' => [
        'status' => 'live',
        'summary' => 'A/B and A/B/C tests on live widgets, with honest statistics.',
        'overview' => [
            'A/B testing splits shoppers between versions of a live widget, such as an upsell, countdown, sticky add to cart, trust block or a checkout or Thank You block, and reports which one earns more. Variants can change the template, design and text, or hide the widget as a holdout; prices and discounts stay as published, so checkout always matches what shoppers saw.',
            'Results show visitors, conversion, revenue, revenue per visitor and AOV per variant, with lift, confidence intervals and p-values. A winner is only named after at least 7 days and 1,000 visitors and 100 conversions per variant, when the primary metric is significant and no guardrail is breached. Then apply the winner in one click.',
        ],
        'benefits' => [
            ['Stable assignment', 'Each shopper sees the same version every visit.'],
            ['Clear results', 'Lift with 95% intervals and p-values; no early winners.'],
            ['Guardrails', 'Cart abandonment and negative interactions can block a "winner".'],
            ['Apply in one click', 'Publish the winning version to everyone when the test is done.'],
        ],
    ],

    'personalization' => [
        'status' => 'live',
        'summary' => 'Segments and rules that show the right widget to each shopper.',
        'overview' => [
            'A first-time visitor and a five-order customer need different nudges. Segments group shoppers by orders, total spent, average order, last order, tags, products bought, market, device and the widgets they used, starting from ten ready-made segments such as new, returning, VIP, high-AOV, at-risk and lapsed.',
            'Rules then show a widget only to an audience, show it in another template, or hide it, with extra conditions for device, cart value and UTM. Segments are shared: target widgets with them, use them as A/B test audiences and check them in workflows. Everything is worked out on your store, without sending shopper data anywhere.',
        ],
        'benefits' => [
            ['Ready-made segments', 'New, returning, first-time, VIP, high-AOV, at-risk, lapsed, mobile and more.'],
            ['Show, swap or hide', 'Rules run in priority order, with conflict warnings.'],
            ['One segment, everywhere', 'Widgets, A/B tests and workflows share the same segments.'],
            ['Member counts', 'Shopify counts customer-based segments for you.'],
        ],
    ],

];
