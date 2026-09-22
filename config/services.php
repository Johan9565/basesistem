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

    'evolution' => [
        'base_url' => env('EVOLUTION_BASE_URL', 'http://evolution-api:8080'),
        'api_key' => env('EVOLUTION_API_KEY'),
        'webhook_secret' => env('EVOLUTION_WEBHOOK_SECRET'),
        'server_url' => env('EVOLUTION_SERVER_URL', 'http://localhost:8081'),
        'webhook_url' => env('EVOLUTION_WEBHOOK_URL', 'http://laravel.test/api/evolution/webhook'),
    ],

    'deepseek' => [
        'key' => env('DEEPSEEK_API_KEY'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
        'max_tokens' => (int) env('DEEPSEEK_MAX_TOKENS', 150),
        'temperature' => (float) env('DEEPSEEK_TEMPERATURE', 0.25),
        'stop' => env('DEEPSEEK_STOP') !== null && env('DEEPSEEK_STOP') !== ''
            ? array_values(array_filter(array_map('trim', explode('|', (string) env('DEEPSEEK_STOP')))))
            : ["\n\n"],
    ],

    'google_calendar' => [
        'calendar_id' => env('GOOGLE_CALENDAR_ID'),
        'credentials_path' => env('GOOGLE_SERVICE_ACCOUNT_PATH', 'storage/app/google/service-account.json'),
        'timezone' => env('GOOGLE_CALENDAR_TIMEZONE', 'America/Merida'),
    ],

    'whatsapp' => [
        // Mensajes a leer de Mongo antes de recortar (margen).
        'history_limit' => (int) env('WHATSAPP_HISTORY_LIMIT', 12),
        // Pares user/assistant a enviar a DeepSeek (2–3 recomendado).
        'history_turns' => (int) env('WHATSAPP_HISTORY_TURNS', 3),
        'inactive_hours' => (int) env('WHATSAPP_INACTIVE_HOURS', 24),
        // Opt-in: no enviar nada automático hasta que lo actives.
        'auto_welcome' => filter_var(env('WHATSAPP_AUTO_WELCOME', false), FILTER_VALIDATE_BOOLEAN),
        'auto_media_reply' => filter_var(env('WHATSAPP_AUTO_MEDIA_REPLY', false), FILTER_VALIDATE_BOOLEAN),
        'welcome_message' => env(
            'WHATSAPP_WELCOME_MESSAGE',
            "¡Hola! Soy el asistente de citas. Escribe qué necesitas (motivo y día/hora) y te ayudo a agendar."
        ),
        'media_message' => env(
            'WHATSAPP_MEDIA_MESSAGE',
            'Por favor, escribe tu mensaje en texto para ayudarte a agendar.'
        ),
    ],

];
