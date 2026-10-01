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

    'deepgram' => [
        'key' => env('DEEPGRAM_API_KEY'),
        'base_url' => env('DEEPGRAM_BASE_URL', 'https://api.deepgram.com/v1'),
        'model' => env('DEEPGRAM_MODEL', 'nova-3'),
        'language' => env('DEEPGRAM_LANGUAGE', 'es'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'whisper_model' => env('GROQ_WHISPER_MODEL', 'whisper-large-v3-turbo'),
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
        // Compresión de contexto en Mongo ("estilo cavernícola" para ahorro de tokens)
        'compress_memory' => filter_var(env('WHATSAPP_COMPRESS_MEMORY', true), FILTER_VALIDATE_BOOLEAN),
        'compress_memory_trigger_turns' => (int) env('WHATSAPP_COMPRESS_TRIGGER_TURNS', 4),
        // Humanizer & Splitter de mensajes
        'split_messages' => filter_var(env('WHATSAPP_SPLIT_MESSAGES', true), FILTER_VALIDATE_BOOLEAN),
        'split_delay_ms' => (int) env('WHATSAPP_SPLIT_DELAY_MS', 1000),
        'human_typing' => filter_var(env('WHATSAPP_HUMAN_TYPING', true), FILTER_VALIDATE_BOOLEAN),
        // Transcripción de audios / notas de voz (Deepgram / Groq / OpenAI)
        'transcribe_audio' => filter_var(env('WHATSAPP_TRANSCRIBE_AUDIO', true), FILTER_VALIDATE_BOOLEAN),
        'transcription_driver' => env('WHATSAPP_TRANSCRIPTION_DRIVER', 'deepgram'), // 'deepgram', 'groq' o 'openai'
        // Tras N horarios no disponibles seguidos, pausa el bot y alerta al staff.
        'friction_escalate_after' => (int) env('WHATSAPP_FRICTION_ESCALATE_AFTER', 3),
        'escalation_courtesy' => env(
            'WHATSAPP_ESCALATION_COURTESY',
            'Déjame revisar ese detalle específico con el equipo para darte la información exacta. En un momento te confirmo por aquí mismo.'
        ),
        // Opcional: WhatsApp del encargado (número) e instancia Evolution para alertas.
        'escalation_phone' => env('WHATSAPP_ESCALATION_PHONE', ''),
        'escalation_instance' => env('WHATSAPP_ESCALATION_INSTANCE', ''),
        // Concurrencia: lock Redis + debounce de ráfagas (ms).
        'process_lock_seconds' => (int) env('WHATSAPP_PROCESS_LOCK_SECONDS', 120),
        'process_lock_wait_seconds' => (int) env('WHATSAPP_PROCESS_LOCK_WAIT_SECONDS', 90),
        'burst_debounce_ms' => (int) env('WHATSAPP_BURST_DEBOUNCE_MS', 2500),
        // Soft-hold de slot al entrar en CONFIRMING (minutos).
        'slot_hold_minutes' => (int) env('WHATSAPP_SLOT_HOLD_MINUTES', 10),
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
