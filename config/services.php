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
        'max_tokens' => (int) env('DEEPSEEK_MAX_TOKENS', 400),
        'temperature' => (float) env('DEEPSEEK_TEMPERATURE', 0.25),
        // Vacío por defecto: "\n\n" cortaba los resúmenes de confirmación a media frase.
        'stop' => array_values(array_filter(array_map(
            'trim',
            explode('|', (string) env('DEEPSEEK_STOP', '')),
        ))),
    ],

    'mimo' => [
        'key' => env('MIMO_API_KEY'),
        'base_url' => env('MIMO_BASE_URL', 'https://api.xiaomimimo.com/v1'),
        'model' => env('MIMO_MODEL', 'mimo-v2-flash'),
        'max_tokens' => (int) env('MIMO_MAX_TOKENS', 400),
        'temperature' => (float) env('MIMO_TEMPERATURE', 0.25),
        'stop' => array_values(array_filter(array_map(
            'trim',
            explode('|', (string) env('MIMO_STOP', '')),
        ))),
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
        'history_turns' => (int) env('WHATSAPP_HISTORY_TURNS', 8),
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
        // Tras N horarios no disponibles seguidos, pausa el bot y alerta al staff.
        'friction_escalate_after' => (int) env('WHATSAPP_FRICTION_ESCALATE_AFTER', 3),
        'escalation_courtesy' => env(
            'WHATSAPP_ESCALATION_COURTESY',
            'Déjame revisar ese detalle específico con el equipo para darte la información exacta. En un momento te confirmo por aquí mismo.'
        ),
        // Opcional: WhatsApp del encargado (número) e instancia Evolution para alertas.
        'escalation_phone' => env('WHATSAPP_ESCALATION_PHONE', ''),
        'escalation_instance' => env('WHATSAPP_ESCALATION_INSTANCE', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram (asistente admin por instancia WhatsApp)
    |--------------------------------------------------------------------------
    |
    | El token del bot vive en cada WhatsappInstance. Aquí solo la URL base
    | pública para registrar el webhook: {base}/api/telegram/{instance}/webhook
    |
    */
    'telegram' => [
        'webhook_base_url' => rtrim(
            (string) (env('TELEGRAM_WEBHOOK_BASE_URL') ?: env('APP_URL', 'http://localhost')),
            '/',
        ),
        'history_limit' => (int) env('TELEGRAM_ADMIN_HISTORY_LIMIT', 12),
        'history_turns' => (int) env('TELEGRAM_ADMIN_HISTORY_TURNS', 4),
    ],

];
