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
            'Cart upsells show in your theme\'s slide-out cart drawer with no block to place, and on the cart page. They hide products already in the cart, let shoppers pick a variant and add with one tap. If you offer an incentive, it applies only to items added from the recommendation, automatically at checkout.',
        ],
        'benefits' => [
            ['Cart drawer and cart page', 'Appears in the slide-out cart automatically, and on the cart page with a block.'],
            ['One-tap add', 'Shoppers add the recommendation without leaving the cart.'],
            ['Incentives that stay fair', 'The discount applies only to items added from the offer.'],
            ['Four layouts', 'Carousel, grid, horizontal or minimal card.'],

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
        'status' => 'soon',
        'summary' => 'Blocks for checkout, Thank You and Order Status pages where Shopify supports them.',
        'overview' => [
            'Checkout blocks bring reviews, trust, shipping progress and offers into Shopify\'s checkout, and turn the Thank You and Order Status pages into reorders, reviews and referrals.',
            'OrderOrbit Space checks what your plan supports and only offers the surfaces you can use. This module is being built now.',
        ],
        'benefits' => [
            ['Only what your plan supports', 'Capability detection shows the checkout surfaces your store can use.'],
            ['After-purchase value', 'Thank You and Order Status blocks for reorders, reviews and referrals.'],
        ],
    ],

    'customer-accounts' => [
        'status' => 'soon',
        'summary' => 'Reorder, reviews and rewards blocks in new customer accounts.',
        'overview' => [
            'Customer account blocks help returning shoppers reorder in one tap, leave reviews and see their rewards — inside Shopify\'s new customer accounts. This module is being built now.',
        ],
        'benefits' => [
            ['One-tap reorder', 'Returning customers reorder past purchases quickly.'],
            ['Reviews and rewards', 'Ask for reviews and show rewards where customers already look.'],
        ],
    ],

    'automation' => [
        'status' => 'soon',
        'summary' => 'Lifecycle emails triggered by what customers do.',
        'overview' => [
            'Lifecycle automation sends the right email at the right moment: a review request after delivery, a reorder reminder, a win-back. Workflows run reliably, with status, retries and logs. This module is being built now.',
        ],
        'benefits' => [
            ['Customer-lifecycle focused', 'Review requests, reorder reminders, win-backs and more.'],
            ['Reliable runs', 'Every run is logged, idempotent and retried when needed.'],
        ],
    ],

    'analytics' => [
        'status' => 'live',
        'summary' => 'Revenue, orders and conversion, and how much came from your offers.',
        'overview' => [
            'OrderOrbit Space measures what matters with a Shopify web pixel that respects your customers\' consent choices. You see store revenue, orders, average order value and conversion rate, and exactly how much revenue came from lines your bundles, gifts and upsells added.',
            'Every offer has its own numbers — views, adds to cart, orders and revenue — so you can keep what works and change what doesn\'t.',
        ],
        'benefits' => [
            ['Revenue from offers', 'Order lines are credited to the offer that added them.'],
            ['Store health', 'Revenue, orders, AOV and conversion rate, compared with the previous period.'],
            ['Per-offer results', 'Views, adds to cart, orders and revenue for each bundle, gift and upsell.'],
            ['Consent-aware', 'Only shoppers who allow analytics are counted; no personal data is sent.'],
        ],
    ],

    'ab-testing' => [
        'status' => 'soon',
        'summary' => 'Test two versions of an offer and keep the winner.',
        'overview' => [
            'A/B testing splits shoppers between versions of a bundle, gift or countdown and reports which one earns more, with honest statistics. This module is being built now.',
        ],
        'benefits' => [
            ['Stable assignment', 'Each shopper sees the same version every visit.'],
            ['Clear results', 'Revenue and conversion per version, with confidence.'],
        ],
    ],

    'personalization' => [
        'status' => 'soon',
        'summary' => 'Show different offers to different audiences.',
        'overview' => [
            'Personalization lets you show different offers to new and returning customers, by market or by traffic source. This module is being built now.',
        ],
        'benefits' => [
            ['Audiences', 'New vs returning, markets, traffic sources.'],
            ['Dynamic offers', 'The right bundle or reward for each audience.'],
        ],
    ],

];
