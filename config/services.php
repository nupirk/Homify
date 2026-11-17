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

    'sms' => [
        'gateway' => env('SMS_GATEWAY', 'log'), // sparrow, twilio, log
        'token' => env('SMS_TOKEN'),
        'from' => env('SMS_FROM', 'Urban'),

        // Sparrow SMS (Nepal)
        'sparrow_token' => env('SPARROW_SMS_TOKEN'),

        // Twilio (International)
        'twilio_sid' => env('TWILIO_ACCOUNT_SID'),
        'twilio_token' => env('TWILIO_AUTH_TOKEN'),
        'twilio_from' => env('TWILIO_FROM_NUMBER'),
    ],

];
