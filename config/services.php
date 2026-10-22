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

    'assistant' => [
        'url' => env('ASSISTANT_AI_URL', env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com')),
        'key' => env('ASSISTANT_AI_KEY', env('DEEPSEEK_API_KEY', '')),
        'model' => env('ASSISTANT_AI_MODEL', env('DEEPSEEK_MODEL', 'deepseek-chat')),
    ],

    'deepgram' => [
        'key' => env('DEEPGRAM_API_KEY', ''),
        'model' => env('DEEPGRAM_MODEL', 'nova-3'),
        'language' => env('DEEPGRAM_LANGUAGE', 'es'),
    ],

    'evolution' => [
        'base_url' => env('EVOLUTION_BASE_URL', 'http://evolution-api:8080'),
        'api_key'  => env('EVOLUTION_API_KEY', ''),
        'instance' => env('EVOLUTION_INSTANCE_NAME', 'default'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
        'chat_id'   => env('TELEGRAM_DEFAULT_CHAT_ID', ''),
    ],

];
