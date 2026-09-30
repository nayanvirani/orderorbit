<?php

/* Pricing page comparison. "Soon" rows are planned features, shown as coming soon. */

return [
    'taglines' => [
        'starter' => 'Your first bundle and gift bar',
        'growth' => 'Unlimited offers for growing stores',
        'scale' => 'Priority support and early access',
    ],
    'rows' => [
        ['Live offers', '5', 'Unlimited', 'Unlimited'],
        ['Bundles / progressive gift campaigns', '1 / 1', 'Unlimited', 'Unlimited'],
        ['Cart upsells, countdowns, sticky add to cart, trust', true, true, true],
        ['Every template and design option', true, true, true],
        ['Analytics (revenue per offer)', true, true, true],
        ['Checkout & Thank You blocks (coming soon)', false, true, true],
        ['A/B testing (coming soon)', false, true, true],
        ['Automation and personalization (coming soon)', false, false, true],
        ['Support', 'Standard', 'Standard', 'Priority'],
    ],
    'faqs' => [
        ['How am I billed?', 'Through your Shopify invoice, monthly. You can change or cancel your plan from Shopify at any time.'],
        ['What happens when I reach a limit?', 'You can\'t publish more offers of that kind until you upgrade. Nothing is deleted.'],
        ['Can I downgrade?', 'Yes. If you have more live offers than the new plan allows, the extras are paused, not deleted.'],
        ['Do I need Shopify Plus?', 'No. Everything that\'s live today works on any Shopify plan. Some future checkout blocks will need Plus, and the app will only show what your store supports.'],
    ],
];
