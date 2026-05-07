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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'youtube' => [
        'key' => env('YOUTUBE_API_KEY', ''),
    ],

    'livekit' => [
        'url' => env('LIVEKIT_URL', ''),
        'key' => env('LIVEKIT_API_KEY', ''),
        'secret' => env('LIVEKIT_API_SECRET', ''),
    ],

    'baglantikal' => [
        'access_pin' => env('BAGLANTIKAL_ACCESS_PIN'),
        'letter_pin' => env('BAGLANTIKAL_LETTER_PIN'),
    ],

    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:noreply@kank.com.tr'),
    ],

    'app_release' => [
        'allowed_hosts' => env('APP_RELEASE_ALLOWED_HOSTS', 'drive.google.com,github.com,githubusercontent.com'),
    ],

];
