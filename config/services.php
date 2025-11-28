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

    'yandex' => [
        'iam_token' => env('YANDEX_IAM_TOKEN'),
        'folder_id' => env('YANDEX_FOLDER_ID'),
    ],

    'imap' => [
        'host' => env('MAIL_IMAP_HOST'),
        'port' => env('MAIL_IMAP_PORT', 993),
        'encryption' => env('MAIL_IMAP_ENCRYPTION', 'ssl'),
        'username' => env('MAIL_IMAP_USERNAME'),
        'password' => env('MAIL_IMAP_PASSWORD'),
        'fetch_limit' => env('MAIL_FETCH_LIMIT', 50),
        'fetch_period_minutes' => env('MAIL_FETCH_PERIOD_MINUTES', 60),
    ],

];
