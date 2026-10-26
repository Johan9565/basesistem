<?php

use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\EvolutionWebhookController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'online',
        'message' => 'Personal Assistant API',
        'version' => 'v1',
    ]);
});

Route::get('/v1', function () {
    return response()->json([
        'status' => 'online',
        'message' => 'Personal Assistant API v1',
        'endpoints' => [
            'interact' => url('/api/v1/assistant/interact'),
            'voice' => url('/api/v1/assistant/voice'),
            'feed' => url('/api/v1/assistant/feed'),
            'agenda' => url('/api/v1/assistant/agenda'),
        ],
    ]);
});

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::match(['get', 'post'], '/status', [AssistantController::class, 'status']);
    Route::post('/interact', [AssistantController::class, 'interact']);

    Route::prefix('assistant')->name('assistant.')->group(function () {
        Route::match(['get', 'post'], '/status', [AssistantController::class, 'status'])->name('status');
        Route::match(['get', 'post'], '/assistant/status', [AssistantController::class, 'status']);
        Route::post('/interact', [AssistantController::class, 'interact'])->name('interact');
        Route::match(['get', 'post'], '/assistant/interact', [AssistantController::class, 'interact']);
        Route::post('/voice', [AssistantController::class, 'voice'])->name('voice');
        Route::get('/messages/{id}', [AssistantController::class, 'getMessageStatus'])->name('messages.status');
        Route::get('/feed', [AssistantController::class, 'feed'])->name('feed');
        Route::get('/agenda', [AssistantController::class, 'agenda'])->name('agenda');
        Route::post('/events', [AssistantController::class, 'storeEvent'])->name('events.store');
        Route::delete('/events/{id}', [AssistantController::class, 'destroyEvent'])->name('events.destroy');

        Route::prefix('google-calendar')->name('google-calendar.')->group(function () {
            Route::get('/settings', [AssistantController::class, 'getGoogleCalendarSettings'])->name('settings');
            Route::post('/settings', [AssistantController::class, 'saveGoogleCalendarSettings'])->name('settings.save');
            Route::post('/sync', [AssistantController::class, 'syncGoogleCalendar'])->name('sync');
            Route::post('/test', [AssistantController::class, 'testGoogleCalendar'])->name('test');
        });
    });

    Route::patch('/reminders/{id}/complete', [AssistantController::class, 'completeReminder'])->name('reminders.complete');
});

Route::post('/evolution/webhook', [EvolutionWebhookController::class, 'handle'])
    ->name('evolution.webhook');

Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle'])
    ->name('whatsapp.webhook');
