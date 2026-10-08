<?php

return [
    // When set, the public website shows a "coming soon" page to everyone except
    // visitors who unlock it with this password (set only as a Railway variable).
    'preview_password' => env('SITE_PREVIEW_PASSWORD'),

    // Whether search engines start allowed, before anything is set in the Internal Admin
    // (Crawlers & SEO, which controls every bot from then on).
    'search_engines' => (bool) env('SITE_SEARCH_ENGINES', false),

    // The main OrderOrbit domain. Growvia (this app and its website) lives on APP_URL, a subdomain;
    // this host shows a "coming soon" page and sends every old public-site link on to Growvia.
    'company_host' => env('COMPANY_HOST', 'orderorbit.space'),
];
