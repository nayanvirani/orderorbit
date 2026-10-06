<?php

return [
    // When set, the public website shows a "coming soon" page to everyone except
    // visitors who unlock it with this password (set only as a Railway variable).
    'preview_password' => env('SITE_PREVIEW_PASSWORD'),

    // Search engines (Google, Bing…) may index the public website. Off: robots.txt, page tags and
    // the server keep every crawler out (link previews still work). AI and SEO bots are always kept out.
    'search_engines' => (bool) env('SITE_SEARCH_ENGINES', false),
];
