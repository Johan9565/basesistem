<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\users as UsersController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\NotificationsController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

use App\Http\Controllers\Whatsapp\WhatsappAssistantController;

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/assistant', function () {
    return Inertia::render('Assistant/Workspace');
})->middleware(['auth', 'verified', 'permission:assistant.workspace'])->name('assistant.workspace');

Route::middleware(['auth', 'verified', 'permission:whatsapp.assistant'])->prefix('whatsapp')->name('whatsapp.')->group(function () {
    Route::get('/', [WhatsappAssistantController::class, 'index'])->name('assistant');
    Route::post('/instance', [WhatsappAssistantController::class, 'storeInstance'])->name('instance.store');
    Route::get('/qr', [WhatsappAssistantController::class, 'getQrCode'])->name('qr');
    Route::post('/settings', [WhatsappAssistantController::class, 'updateSettings'])->name('settings.update');
    Route::post('/disconnect', [WhatsappAssistantController::class, 'disconnect'])->name('disconnect');
    Route::delete('/instance', [WhatsappAssistantController::class, 'deleteInstance'])->name('instance.destroy');
    Route::post('/test-message', [WhatsappAssistantController::class, 'testMessage'])->name('test-message');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::post('/profile/banner', [ProfileController::class, 'updateBanner'])->name('profile.banner');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
Route::middleware(['auth', 'permission:administration'])->group(function () {
    Route::middleware(['auth', 'permission:users'])->group(function () {
        Route::get('/users', [UsersController::class, 'index'])->name('users');
        Route::post('/users', [UsersController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UsersController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UsersController::class, 'destroy'])->name('users.destroy');
    });
    Route::middleware(['auth', 'permission:roles'])->group(function () {
        Route::get('/roles', [RolesController::class, 'index'])->name('roles');
        Route::patch('/roles/{role}/permissions', [RolesController::class, 'updatePermissions'])->name('roles.permissions.update');
    });
});
Route::middleware(['auth'])->group(function () {
    Route::post('/theme', [ThemeController::class, 'updateActiveTheme'])->name('theme.update');

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/feed', [NotificationsController::class, 'feed'])->name('feed');
        Route::patch('/{notification}/read', [NotificationsController::class, 'markAsRead'])->name('read');
        Route::post('/mark-all-read', [NotificationsController::class, 'markAllRead'])->name('mark-all-read');
    });
});


require __DIR__ . '/auth.php';
