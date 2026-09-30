<?php

/* Pricing page comparison — Combined MVP Scope, Part E4. */

return [
    'taglines' => [
        'starter' => 'Get your first experiences live',
        'growth' => 'Everything to grow AOV and test',
        'scale' => 'Personalize and scale',
    ],
    'rows' => [
        ['Active experiences', '5', 'Unlimited', 'Unlimited'],
        ['Bundles / progressive gift campaigns', '1 / 1', 'Unlimited', 'Unlimited'],
        ['Workflows / executions per month', '5 / 1,000', 'Advanced / 10,000', 'Advanced / higher limit'],
        ['Analytics', 'Basic', 'Advanced', 'Advanced'],
        ['Checkout & Thank You blocks', false, true, true],
        ['A/B testing', false, true, true],
        ['Personalization', false, false, 'Advanced'],
        ['Customer Account blocks', false, false, true],
        ['Support', 'Standard', 'Standard', 'Priority'],
    ],
    'faqs' => [
        ['How am I billed?', 'Through your Shopify invoice.'],
        ['What happens at my limit?', 'New items pause until you upgrade; nothing is deleted.'],
        ['Can I downgrade?', 'Yes; items over the new limit are paused.'],
        ['Is there a free trial?', 'Trial length to be set at launch.'],
        ['Do I need Shopify Plus?', 'Only for in-checkout blocks.'],
    ],
];
