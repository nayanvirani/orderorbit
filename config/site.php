<?php

return [
    // When set, the public website shows a "coming soon" page to everyone except
    // visitors who unlock it with this password (set only as a Railway variable).
    'preview_password' => env('SITE_PREVIEW_PASSWORD'),

    // Whether search engines start allowed, before anything is set in the Internal Admin
    // (Crawlers & SEO, which controls every bot from then on).
    'search_engines' => (bool) env('SITE_SEARCH_ENGINES', false),
];
