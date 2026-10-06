<?php

/*
| Error pages. These are shown even when the database is down, in which case the built-in
| text below is used. Links: a path or javascript:history.back() / javascript:location.reload().
*/

return [
    '404' => ['title' => 'Page not found', 'code' => 'Error 404', 'heading' => 'This page drifted *out of orbit.*', 'message' => 'The link may be broken, or the page has moved.',
        'links' => [['label' => 'Go to homepage', 'href' => '/'], ['label' => 'Visit Help Center', 'href' => '/help']]],
    '403' => ['title' => 'No access', 'code' => 'Error 403', 'heading' => 'You don\'t have *access* here.', 'message' => 'Your account isn\'t allowed to open this page. If you think it should, ask whoever manages your account, or contact us.',
        'links' => [['label' => 'Go to homepage', 'href' => '/'], ['label' => 'Contact us', 'href' => '/contact']]],
    '419' => ['title' => 'Page expired', 'code' => 'Error 419', 'heading' => 'This page *expired.*', 'message' => 'For your security, forms expire after a while. Go back, refresh the page and try again.',
        'links' => [['label' => 'Go back', 'href' => 'javascript:history.back()'], ['label' => 'Go to homepage', 'href' => '/']]],
    '429' => ['title' => 'Too many requests', 'code' => 'Error 429', 'heading' => 'Slow down a *little.*', 'message' => 'We received a lot of requests from you in a short time. Wait a minute, then try again.',
        'links' => [['label' => 'Go to homepage', 'href' => '/']]],
    '500' => ['title' => 'Something went wrong', 'code' => 'Error 500', 'heading' => 'Something went *wrong.*', 'message' => 'An unexpected error happened on our side. Please try again in a moment, and contact us if it keeps happening.',
        'links' => [['label' => 'Go to homepage', 'href' => '/'], ['label' => 'Contact us', 'href' => '/contact']]],
    '503' => ['title' => 'Back soon', 'code' => 'Maintenance', 'heading' => 'We\'ll be *right back.*', 'message' => 'OrderOrbit Space is being updated. Offers already live on your store keep showing. This page will be back in a few minutes.',
        'links' => [['label' => 'Try again', 'href' => 'javascript:location.reload()']]],
];
