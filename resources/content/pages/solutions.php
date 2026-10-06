<?php

/*
| Solutions page and the labels on each solution page. Each solution's own text is edited
| under "Solution pages".
*/

return [
    'seo_title' => 'Solutions | OrderOrbit Space',
    'seo_description' => 'How DTC, repeat-purchase, fashion and Shopify Plus brands use OrderOrbit Space to raise order value.',
    'eyebrow' => 'Solutions',
    'title' => 'A setup for *your kind of store.*',
    'lead' => 'Pick the model closest to yours to see which offers to start with and why.',
    'card_link' => 'See the setup →',
    'start' => [
        'title' => 'Not sure where to start?',
        'text' => 'Most stores begin with a quantity-break bundle on their best-selling product and a free-shipping bar.',
        'primary' => ['label' => 'Install on Shopify', 'href' => '{install}'],
        'secondary' => ['label' => 'Ask for a recommendation', 'href' => '/contact'],
        'plan' => [
            ['when' => 'Week 1', 'title' => 'Quantity-break bundle', 'text' => 'On your best seller.'],
            ['when' => 'Week 1', 'title' => 'Free-shipping bar', 'text' => 'In the cart.'],
            ['when' => 'Week 2', 'title' => 'Cart upsell', 'text' => 'A matching add-on.'],
            ['when' => 'Week 3', 'title' => 'Check the numbers', 'text' => 'Keep what earns.'],
        ],
    ],
    // Labels on each solution page. {name} is the solution's name.
    'detail' => [
        'breadcrumb_home' => 'Home',
        'eyebrow' => 'For {name}',
        'install' => ['label' => 'Install on Shopify', 'href' => '{install}'],
        'pricing' => ['label' => 'See pricing', 'href' => '/pricing'],
        'overview_title' => 'Overview',
        'setup_title' => 'A setup that works',
        'features_title' => 'Features used',
        'learn_more' => 'Learn more →',
        'faq_title' => 'Questions',
        'cta_title' => 'Set it up on your store',
        'cta_text' => 'Install OrderOrbit Space and publish your first offer in a few minutes.',
    ],
];
