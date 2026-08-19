<?php

return [
    'provider' => env('IA_EVALUATOR_PROVIDER', 'deepseek'),
    'cuota_gratis' => (int) env('IA_CUOTA_GRATIS', 0),
    'cuota_premium_diaria' => (int) env('IA_CUOTA_PREMIUM_DIARIA', 30),
    'examen_prueba_preguntas' => (int) env('IA_EXAMEN_PRUEBA_PREGUNTAS', 10),
    'examen_prueba_intentos' => (int) env('IA_EXAMEN_PRUEBA_INTENTOS', 3),
    'timeout' => (int) env('IA_EVALUATOR_TIMEOUT', 20),
    'deepseek' => [
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        'api_key' => env('DEEPSEEK_API_KEY'),
        'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
    ],
    'gemini' => [
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('IA_GEMINI_MODEL', 'gemini-2.0-flash'),
    ],
];
