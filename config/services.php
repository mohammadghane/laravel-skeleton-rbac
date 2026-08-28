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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * وب‌سرویس پرسنلی سازمان - مبدأ همگام‌سازی پرسنل، انواع قرارداد و سمت‌ها.
     * نگاه کنید به App\Services\PersonnelSyncService و دستور overtime:sync-personnel.
     */
    'personnel' => [
        'url' => env('PERSONNEL_SYNC_URL', 'http://127.0.0.1/api/getpersonels.json'),
        'timeout' => env('PERSONNEL_SYNC_TIMEOUT', 30),
        // دامنه‌ی ایمیل جایگزین: سرویس ایمیل نمی‌دهد ولی ستون NOT NULL است.
        'email_domain' => env('PERSONNEL_EMAIL_DOMAIN', 'personnel.local'),
    ],

];
