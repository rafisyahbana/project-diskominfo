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

    'fonnte' => [
        'token' => env('FONNTE_API_TOKEN'),
        'secret' => env('FONNTE_WEBHOOK_SECRET'),
        'device' => env('FONNTE_DEVICE'),
    ],

    'tesseract' => [
        // Path lengkap ke binary tesseract. Di Windows: 'C:\Program Files\Tesseract-OCR\tesseract.exe'
        // Di Linux/production: cukup 'tesseract' jika sudah di PATH
        'path'     => env('TESSERACT_PATH', 'tesseract'),
        // Bahasa yang dipakai — 'ind+eng' untuk KTP Indonesia
        'lang'     => env('TESSERACT_LANG', 'ind+eng'),
        // Direktori tessdata (opsional, null = pakai default bawaan binary)
        'tessdata' => env('TESSERACT_TESSDATA', null),
    ],

];
