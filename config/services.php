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

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business API (PRD §16)
    |--------------------------------------------------------------------------
    |
    | Official Meta/BSP integration — never a personal WhatsApp number.
    | Credentials are read from env only (never committed); the base URL
    | and phone_number_id identify the sender number registered with Meta.
    |
    */
    'whatsapp' => [
        'base_url' => env('WHATSAPP_API_BASE_URL', 'https://graph.facebook.com/v19.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'token' => env('WHATSAPP_API_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Xendit Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Powers Portal Anggota's self-service "Setor Simpanan" and "Bayar
    | Angsuran" — member is redirected to a Xendit-hosted Invoice checkout
    | page, and a webhook confirms payment server-to-server. secret_key
    | empty means the gateway is not configured yet; the portal hides the
    | payment buttons rather than showing a broken flow.
    |
    */
    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'webhook_token' => env('XENDIT_WEBHOOK_VERIFICATION_TOKEN'),
        'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
    ],

];
