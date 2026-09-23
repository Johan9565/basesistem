<?php

use App\Http\Controllers\Api\EvolutionWebhookController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Middleware\VerifyEvolutionWebhookSecret;
use App\Http\Middleware\VerifyTelegramWebhookSecret;
use Illuminate\Support\Facades\Route;

Route::post('/evolution/webhook', EvolutionWebhookController::class)
    ->middleware(VerifyEvolutionWebhookSecret::class)
    ->name('evolution.webhook');

Route::post('/telegram/{instance}/webhook', TelegramWebhookController::class)
    ->middleware(VerifyTelegramWebhookSecret::class)
    ->name('telegram.webhook');
