<?php

use App\Http\Controllers\Api\EvolutionWebhookController;
use App\Http\Middleware\VerifyEvolutionWebhookSecret;
use Illuminate\Support\Facades\Route;

Route::post('/evolution/webhook', EvolutionWebhookController::class)
    ->middleware(VerifyEvolutionWebhookSecret::class)
    ->name('evolution.webhook');
