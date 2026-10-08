<?php

/*
| Pricing page. The headline, intro and billing notes are shared with the in-app plan picker
| and edited in Plans & features → Pricing page text; plans, prices, plan lines and the
| comparison table come from Plans & features. The billing questions are under Lists →
| Pricing questions.
*/

return [
    'seo_title' => 'Pricing | Growvia',
    'eyebrow' => 'Pricing',
    'per_month' => '/mo',
    'free' => '$0',
    'free_note' => 'Free forever',
    'paid_note' => 'Billed through Shopify',
    'popular' => 'Most popular',
    'free_button' => 'Start free',
    'paid_button' => 'Install on Shopify',
    'compare_free' => 'Free',
    'faq_title' => 'Questions about billing',
    'cta' => [
        'title' => 'Start with the plan that fits today',
        'text' => 'Nothing you\'ve built is ever deleted when you change plans.',
        'primary' => ['label' => 'Install on Shopify', 'href' => '{install}'],
    ],
];
