<?php

$systemMode = strtolower(trim((string) env('SYSTEM_MODE', 'development')));
$isSecureMode = in_array($systemMode, ['deployment', 'production', 'prod'], true);

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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret' => env('RECAPTCHA_SECRET'),
    ],

    'system' => [
        'mode' => $systemMode,
        'otp_enabled' => $isSecureMode,
        'captcha_enabled' => $isSecureMode,
    ],

    'zoom' => [
        'account_id'    => env('ZOOM_ACCOUNT_ID'),
        'client_id'     => env('ZOOM_CLIENT_ID'),
        'client_secret' => env('ZOOM_CLIENT_SECRET'),
        'user_id'       => env('ZOOM_USER_ID', 'me'),
    ],

    'google' => [
        'client_id'          => env('GOOGLE_CLIENT_ID'),
        'client_secret'      => env('GOOGLE_CLIENT_SECRET'),
        'credentials_path'   => env('GOOGLE_APPLICATION_CREDENTIALS', 'storage/app/google/google-service-account.json'),
        'client_secret_path' => env('GOOGLE_CLIENT_SECRET_PATH', 'storage/app/google/client_secret.json'),
        'token_path'         => env('GOOGLE_TOKEN_PATH', 'storage/app/google/token.json'),
        'calendar_id'        => env('GOOGLE_CALENDAR_ID', 'primary'),
        'impersonate_email'  => env('GOOGLE_IMPERSONATE_EMAIL'),
    ],

];
