<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    /*
     * Public, keyless Steam Store endpoints used once by `catalog:fetch-steam`
     * to build the committed catalog seed (database/data/games.json).
     */
    'steam' => [
        'store_url' => env('STEAM_STORE_URL', 'https://store.steampowered.com'),
        'asset_url' => env('STEAM_ASSET_URL', 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps'),
        'country_code' => env('STEAM_COUNTRY_CODE', 'lt'),
        'language' => env('STEAM_LANGUAGE', 'english'),
        'request_delay_ms' => (int) env('STEAM_REQUEST_DELAY_MS', 1500),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
