<?php

return [

    // Claude for the AI features (Morning Brief, offer ideas). The key is a Railway variable,
    // never in the code. Monthly AI budget per store in USD, by plan (0 = no AI).
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
        'budget' => ['premium' => (float) env('AI_BUDGET_PREMIUM', 5)],
    ],


    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    // Railway's public API, for hosting costs in Finance (Internal Admin). An account token from
    // railway.com/account/tokens; it can also be saved in the admin.
    'railway' => [
        'token' => env('RAILWAY_API_TOKEN'),
        // Set by Railway in every deployment: the project whose cost Finance counts.
        'project_id' => env('RAILWAY_PROJECT_ID'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
