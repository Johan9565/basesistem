<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\users as UsersController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\ComponentsController;
use App\Http\Controllers\DependenciesController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\OcrController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamAttemptController;
use App\Http\Controllers\ExamImportController;
use App\Http\Controllers\EvaluarRespuestaController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('permission:exams.import')->group(function () {
        Route::get('/exams/import', [ExamImportController::class, 'create'])->name('exams.import');
        Route::post('/exams/import', [ExamImportController::class, 'store'])->name('exams.import.store');
        Route::get('/exams/template', [ExamImportController::class, 'template'])->name('exams.template');
    });

    Route::get('/exams/{exam}', [ExamController::class, 'show'])->name('exams.show');
    Route::post('/exams/{exam}/attempts', [ExamAttemptController::class, 'store'])->name('exams.attempts.store');
    Route::get('/exams/{exam}/attempts/{attempt}', [ExamAttemptController::class, 'show'])->name('exams.attempts.show');
    Route::patch('/exams/{exam}/attempts/{attempt}', [ExamAttemptController::class, 'update'])->name('exams.attempts.update');
    Route::post('/exams/{exam}/attempts/{attempt}/check', [ExamAttemptController::class, 'check'])->name('exams.attempts.check');
    Route::post('/exams/{exam}/attempts/{attempt}/submit', [ExamAttemptController::class, 'submit'])->name('exams.attempts.submit');
    Route::get('/exams/{exam}/attempts/{attempt}/result', [ExamAttemptController::class, 'result'])->name('exams.attempts.result');
    Route::post('/api/evaluar-respuesta', [EvaluarRespuestaController::class, 'store'])
        ->middleware('esUsuarioPremium')
        ->name('api.evaluar-respuesta');
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
    Route::middleware(['auth', 'permission:components'])->group(function () {
        Route::get('/components', [ComponentsController::class, 'index'])->name('components');
        Route::patch('/components/theme', [ComponentsController::class, 'updateTheme'])->name('components.theme.update');
        Route::patch('/components/active-theme', [ComponentsController::class, 'updateActiveTheme'])->name('components.active-theme.update');
        Route::patch('/components/branding', [ComponentsController::class, 'updateBranding'])->name('components.branding.update');
        Route::post('/components/branding/upload', [ComponentsController::class, 'uploadBrandingAsset'])->name('components.branding.upload');
    });
    Route::middleware(['auth', 'permission:dependencies'])->group(function () {
        Route::get('/dependencies', [DependenciesController::class, 'index'])->name('dependencies');
        Route::post('/dependencies', [DependenciesController::class, 'store'])->name('dependencies.store');
        Route::patch('/dependencies/{dependency}', [DependenciesController::class, 'update'])->name('dependencies.update');
        // Route::delete('/dependencies/{dependency}', [DependenciesController::class, 'destroy'])->name('dependencies.destroy');
    });
});
Route::middleware(['auth', 'permission:ocr'])->group(function () {
    Route::get('/ocr', [OcrController::class, 'index'])->name('ocr');
    Route::post('/ocr', [OcrController::class, 'extract'])->name('ocr.extract');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/theme', [ComponentsController::class, 'updateActiveTheme'])->name('theme.update');

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/feed', [NotificationsController::class, 'feed'])->name('feed');
        Route::patch('/{notification}/read', [NotificationsController::class, 'markAsRead'])->name('read');
        Route::post('/mark-all-read', [NotificationsController::class, 'markAllRead'])->name('mark-all-read');
    });
});


require __DIR__ . '/auth.php';
