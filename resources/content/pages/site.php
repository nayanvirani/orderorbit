<?php

/*
| Header, menus and footer on every public page. Text may use *highlight*, **bold** and
| [link text](/path). Links can be a path, a full URL, or {install} / {signin} for the Shopify links.
| {year} is replaced with the current year.
*/

return [
    'menu' => [
        'product' => 'Product',
        'solutions' => 'Solutions',
        'templates' => 'Templates',
        'pricing' => 'Pricing',
        'resources' => 'Resources',
        'signin' => 'Sign in',
        'install' => 'Install app',
        'install_mobile' => 'Install on Shopify',
    ],
    'product_menu' => [
        'convert' => 'Raise order value',
        'checkout' => 'After the Buy button',
        'grow' => 'Measure & grow',
        'templates_title' => 'Start from a template',
        'templates_text' => '{templates} ready-made designs to customise →',
    ],
    'solutions_menu' => [
        'all_title' => 'All solutions',
        'all_text' => 'Find the setup that fits your store',
    ],
    'resources_menu' => [
        ['label' => 'How it works', 'text' => 'From install to measured results', 'href' => '/how-it-works'],
        ['label' => 'Help Center', 'text' => 'Setup guides and troubleshooting', 'href' => '/help'],
        ['label' => 'Docs', 'text' => 'Step-by-step guides for every feature', 'href' => '/docs'],
        ['label' => 'Blog', 'text' => 'Shopify conversion, explained', 'href' => '/blog'],
    ],
    'footer' => [
        'tagline' => 'Bundles, gifts, upsells and countdowns that raise order value — and show what they earn.',
        'button' => ['label' => 'Install on Shopify', 'href' => '{install}'],
        'columns' => [
            ['title' => 'Product', 'links' => [
                ['label' => 'All features', 'href' => '/features'],
                ['label' => 'Templates', 'href' => '/templates'],
                ['label' => 'How it works', 'href' => '/how-it-works'],
                ['label' => 'Pricing', 'href' => '/pricing'],
            ]],
            ['title' => 'Solutions', 'links' => [
                ['label' => 'DTC brands', 'href' => '/solutions/dtc-brands'],
                ['label' => 'Repeat-purchase brands', 'href' => '/solutions/repeat-purchase'],
                ['label' => 'Fashion & apparel', 'href' => '/solutions/fashion-apparel'],
                ['label' => 'Shopify Plus', 'href' => '/solutions/shopify-plus'],
            ]],
            ['title' => 'Resources', 'links' => [
                ['label' => 'Help Center', 'href' => '/help'],
                ['label' => 'Docs', 'href' => '/docs'],
                ['label' => 'Blog', 'href' => '/blog'],
                ['label' => 'Resources', 'href' => '/resources'],
            ]],
            ['title' => 'Company', 'links' => [
                ['label' => 'About', 'href' => '/about'],
                ['label' => 'Contact', 'href' => '/contact'],
                ['label' => 'Security', 'href' => '/security'],
                ['label' => 'Legal', 'href' => '/legal'],
            ]],
        ],
        'copyright' => '© {year} Growvia. Built for Shopify.',
        'note' => 'Placed in the Theme Editor · Prices applied at checkout · Consent-aware analytics',
    ],
];
