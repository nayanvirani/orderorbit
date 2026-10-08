<?php

/*
| Features page and the labels on every feature page. The text of each feature
| (name, summary, intro, benefits, steps, questions…) is edited under "Feature pages".
*/

return [
    'seo_title' => 'Features | Growvia — Bundles, Gifts, Upsells for Shopify',
    'seo_description' => 'Bundles, progressive gifts, cart upsells, countdowns, sticky add-to-cart, trust blocks and analytics for Shopify, in one app.',
    'breadcrumb' => 'Features',
    'title' => 'Everything that raises order value, *in one app.*',
    'lead' => 'Each feature has ready-made templates, shares your store\'s design and applies its savings at checkout.',
    'groups' => [
        'convert' => ['title' => 'Raise order value and conversion', 'text' => 'On your product pages and in the cart.', 'tab' => 'Order value & conversion'],
        'grow' => ['title' => 'Measure and grow', 'text' => 'See what each offer earns.', 'tab' => 'Measure & grow'],
        'checkout' => ['title' => 'After the Buy button', 'text' => 'Checkout, Thank You and customer accounts.', 'tab' => 'After the Buy button'],
    ],
    'learn_more' => 'Learn more →',
    'cta' => [
        'title' => 'Start with one feature',
        'text' => 'Most stores begin with a bundle or a gift bar and add the rest later. You can start on the Free plan and upgrade when you need more.',
        'primary' => ['label' => 'Install on Shopify', 'href' => '{install}'],
        'secondary' => ['label' => 'See pricing', 'href' => '/pricing'],
    ],
    // Labels on each feature page (/features/…). {name} is the feature's name; {count} the number of templates.
    'detail' => [
        'breadcrumb_home' => 'Home',
        'install' => ['label' => 'Install on Shopify', 'href' => '{install}'],
        'templates_button' => 'See {count} templates',
        'guide_button' => 'Read the guide',
        'where' => 'Shows on',
        'example_results' => 'Example results',
        'example_note' => 'Sample data, for illustration',
        'overview_eyebrow' => 'Overview',
        'overview_title' => 'Clear offers that work with your theme.',
        'how_title' => 'How it works',
        'step' => 'Step',
        'example' => 'Example:',
        'templates_title' => '{count} ready-made templates',
        'templates_text' => 'Pick one in the app, then change the text, colours, sizes and spacing to match your store.',
        'templates_link' => 'Browse all templates →',
        'templates_locked' => 'Every {name} template is inside the app. Install free and preview each one on your own products before you choose.',
        'templates_install' => 'Install free and see them all',
        'faq_title' => 'Questions',
        'related_title' => 'Works well with',
        'cta_title' => 'Try {name} on your store',
        'cta_button' => ['label' => 'Install on Shopify', 'href' => '{install}'],
    ],
];
