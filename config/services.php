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

        // احراز هویت OAuth سرویس پرسنلی.
        //
        // این مقادیر پیش‌تر مستقیم داخل PersonnelSyncService نوشته شده بودند؛ چون
        // مخزن عمومی است، client_secret و رمز عبور نباید در سورس باشند. حالا از
        // .env خوانده می‌شوند (و .env در .gitignore است).
        'token_url' => env('PERSONNEL_TOKEN_URL'),
        'client_id' => env('PERSONNEL_CLIENT_ID'),
        'client_secret' => env('PERSONNEL_CLIENT_SECRET'),
        'username' => env('PERSONNEL_WS_USERNAME'),
        'password' => env('PERSONNEL_WS_PASSWORD'),
        // گواهی SSL سرویس داخلی معتبر نیست؛ فقط در صورت نیاز true کنید.
        'skip_ssl_verify' => env('PERSONNEL_SKIP_SSL_VERIFY', false),
    ],

];
