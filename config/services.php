<?php

return [

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

    // WhatsApp vía WAHA (https://waha.devlike.pro), autoalojado en Coolify.
    'waha' => [
        'enabled' => (bool) env('WAHA_ENABLED', false),
        'url' => rtrim((string) env('WAHA_URL', 'http://waha:3000'), '/'),
        'key' => env('WAHA_API_KEY'),
        'session' => env('WAHA_SESSION', 'default'),
        'country_code' => env('WAHA_COUNTRY_CODE', '57'),
        // Segundos entre mensajes en envíos masivos: cuida el número de un bloqueo.
        'spacing' => (int) env('WAHA_SPACING_SECONDS', 12),
    ],

];
