<?php

use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\AssistantModelAdminController;
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
        Route::get('/conversations', [AssistantController::class, 'conversations'])->name('conversations');

        // Dynamic AI Model Management & Router Admin
        Route::prefix('admin/models')->name('admin.models.')->group(function () {
            Route::get('/', [AssistantModelAdminController::class, 'index'])->name('index');
            Route::post('/', [AssistantModelAdminController::class, 'store'])->name('store');
            Route::post('/test', [AssistantModelAdminController::class, 'test'])->name('test');
            Route::put('/{id}', [AssistantModelAdminController::class, 'update'])->name('update');
            Route::delete('/{id}', [AssistantModelAdminController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/activate', [AssistantModelAdminController::class, 'activate'])->name('activate');
        });
        Route::get('/agenda', [AssistantController::class, 'agenda'])->name('agenda');
        Route::post('/events', [AssistantController::class, 'storeEvent'])->name('events.store');
        Route::delete('/events/{id}', [AssistantController::class, 'destroyEvent'])->name('events.destroy');

        // Entity Edit & Update routes
        Route::patch('/entities/{type}/{id}', [AssistantController::class, 'updateEntity'])->name('entities.update');
        Route::patch('/events/{id}', [AssistantController::class, 'updateEvent'])->name('events.update');
        Route::patch('/reminders/{id}', [AssistantController::class, 'updateReminder'])->name('reminders.update');
        Route::patch('/notes/{id}', [AssistantController::class, 'updateNote'])->name('notes.update');
        Route::patch('/tasks/{id}', [AssistantController::class, 'updateTask'])->name('tasks.update');

        Route::prefix('google-calendar')->name('google-calendar.')->group(function () {
            Route::get('/settings', [AssistantController::class, 'getGoogleCalendarSettings'])->name('settings');
            Route::post('/settings', [AssistantController::class, 'saveGoogleCalendarSettings'])->name('settings.save');
            Route::post('/sync', [AssistantController::class, 'syncGoogleCalendar'])->name('sync');
            Route::post('/test', [AssistantController::class, 'testGoogleCalendar'])->name('test');
        });
    });

    Route::patch('/reminders/{id}/complete', [AssistantController::class, 'completeReminder'])->name('reminders.complete');

    // Root-level entity update endpoints
    Route::patch('/entities/{type}/{id}', [AssistantController::class, 'updateEntity'])->name('entities.update.root');
    Route::patch('/events/{id}', [AssistantController::class, 'updateEvent'])->name('events.update.root');
    Route::patch('/reminders/{id}', [AssistantController::class, 'updateReminder'])->name('reminders.update.root');
    Route::patch('/notes/{id}', [AssistantController::class, 'updateNote'])->name('notes.update.root');
    Route::patch('/tasks/{id}', [AssistantController::class, 'updateTask'])->name('tasks.update.root');
});

Route::post('/evolution/webhook', [EvolutionWebhookController::class, 'handle'])
    ->name('evolution.webhook');

Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle'])
    ->name('whatsapp.webhook');
