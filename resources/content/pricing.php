<?php

/*
| Pricing page copy. Every plan has every feature; plans differ by the store's total sales
| over the last 30 days (config/shopify.php → billing.plans.*.sales_limit).
*/

return [
    'taglines' => [
        'free' => 'For new stores',
        'starter' => 'For stores finding their feet',
        'growth' => 'For growing stores',
        'scale' => 'For established stores',
    ],
    'rows' => [
        ['Monthly store sales', 'Up to $1,000', 'Up to $8,000', 'Up to $20,000', 'Unlimited'],
        ['Bundles and progressive gifts', true, true, true, true],
        ['Cart upsells, countdowns, sticky add to cart, trust', true, true, true, true],
        ['Pre-orders and sales pop', true, true, true, true],
        ['Unlimited offers', true, true, true, true],
        ['Every template and design option', true, true, true, true],
        ['Analytics (revenue per offer)', true, true, true, true],
        ['New features as they are released', true, true, true, true],
        ['Support', 'Standard', 'Standard', 'Standard', 'Priority'],
    ],
    'faqs' => [
        ['What counts as "monthly store sales"?', 'Your store\'s total sales from all orders over the last 30 days, converted to US dollars. Test orders and cancelled orders don\'t count, and refunds are taken off.'],
        ['Are any features locked on cheaper plans?', 'No. Every plan, including Free, has every feature, every template and unlimited offers. You only move up when your store grows.'],
        ['What happens when my store passes its plan\'s limit?', 'Nothing stops straight away. The app tells you, and you have 7 days to upgrade. After that your offers pause until you upgrade; nothing is deleted, and they go live again the moment you do.'],
        ['How am I billed?', 'Through your Shopify invoice, monthly. You can change or cancel your plan from Shopify at any time.'],
        ['Can I downgrade?', 'Yes, whenever your store\'s sales fit the lower plan.'],
        ['Do I need Shopify Plus?', 'No. Everything that\'s live today works on any Shopify plan.'],
    ],
];
