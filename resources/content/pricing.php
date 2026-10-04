<?php

/*
| Pricing page copy. Plans step up by features and by the store's total sales in each 30-day
| cycle (config/shopify.php → billing.plans). "Coming soon" rows are planned features.
*/

return [
    'taglines' => [
        'free' => 'Try it on a new store',
        'starter' => 'The full storefront toolkit',
        'growth' => 'Checkout and post-purchase',
        'scale' => 'For established stores',
    ],
    'rows' => [
        ['Monthly store sales', 'Up to $1,000', 'Up to $8,000', 'Up to $20,000', 'Unlimited'],
        ['Countdown, sticky add to cart, trust badges, sales pop', true, true, true, true],
        ['Bundles', '1 live', 'Unlimited', 'Unlimited', 'Unlimited'],
        ['Progressive gifts', '1 live', 'Unlimited', 'Unlimited', 'Unlimited'],
        ['Cart upsells', '1 live', 'Unlimited', 'Unlimited', 'Unlimited'],
        ['Pre-orders', '1 live', 'Unlimited', 'Unlimited', 'Unlimited'],
        ['Every template and design option', true, true, true, true],
        ['Analytics', 'Store totals', 'Revenue per offer', 'Revenue per offer', 'Revenue per offer'],
        ['Event Explorer, funnels, attribution and customer journeys', false, false, true, true],
        ['Checkout, Thank You & Order Status blocks', false, false, true, true],
        ['Customer account blocks', false, false, true, true],
        ['A/B and A/B/C testing', false, false, true, true],
        ['Lifecycle automation workflows', false, false, false, true],
        ['Personalization (coming soon)', false, false, false, true],
        ['Support', 'Standard', 'Standard', 'Standard', 'Priority'],
    ],
    'faqs' => [
        ['What can I do on the Free plan?', 'Countdowns, sticky add to cart, trust badges and sales pop are unlimited. You can also run one live bundle, one progressive gifts campaign, one cart upsell and one pre-order, with every template. It stays free while your store sells up to $1,000 per cycle.'],
        ['What counts as "monthly store sales"?', 'Your store\'s total sales from all orders in the current 30-day cycle, converted to US dollars. Cycles start on the day you install the app. Test orders and cancelled orders don\'t count, and refunds are taken off.'],
        ['What happens when my store passes its plan\'s sales limit?', 'The app tells you as soon as it happens, even early in the cycle, and you have 3 days to upgrade. After that every feature stops until you upgrade. Nothing is deleted, and everything goes live again the moment you do.'],
        ['Does the count start again each cycle?', 'Yes, every 30 days. If your store was stopped for passing its limit, it stays stopped until you upgrade.'],
        ['Which plan gets the features that are coming soon?', 'Personalization will be on Scale, where lifecycle automation is available now. They appear in the app as they are released, at no extra charge on those plans.'],
        ['How am I billed?', 'Through your Shopify invoice, monthly. You can change or cancel your plan from Shopify at any time.'],
        ['Can I downgrade?', 'Yes, whenever your store\'s sales fit the lower plan. Offers the lower plan doesn\'t cover are paused, not deleted.'],
        ['Do I need Shopify Plus?', 'No. Everything that\'s live today works on any Shopify plan. Blocks inside checkout need Shopify Plus; Thank You and Order Status blocks don\'t.'],
    ],
];
