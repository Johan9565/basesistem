<?php

use App\Http\Controllers\ComponentsController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\users as UsersController;
use App\Http\Controllers\Whatsapp\CalendarController as WhatsappCalendarController;
use App\Http\Controllers\Whatsapp\ConversationsController as WhatsappConversationsController;
use App\Http\Controllers\Whatsapp\InstancesController as WhatsappInstancesController;
use App\Http\Controllers\Whatsapp\MessagesController as WhatsappMessagesController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

use App\Http\Controllers\CompanyContextController;

Route::middleware('auth')->group(function () {
    Route::post('/companies/switch/{company}', [CompanyContextController::class, 'switch'])->name('companies.switch');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::post('/profile/banner', [ProfileController::class, 'updateBanner'])->name('profile.banner');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
use App\Http\Controllers\CompaniesController;
use App\Http\Controllers\EmployeesController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\ServicesController;

Route::middleware(['auth', 'permission:administration'])->group(function () {
    Route::get('/companies', [CompaniesController::class, 'index'])->name('companies');
    Route::post('/companies', [CompaniesController::class, 'store'])->name('companies.store');
    Route::patch('/companies/{company}', [CompaniesController::class, 'update'])->name('companies.update');
    Route::post('/companies/{company}/toggle-module', [CompaniesController::class, 'toggleModule'])->name('companies.toggle-module');

    Route::middleware(['auth', 'permission:users'])->group(function () {
        Route::get('/users', [UsersController::class, 'index'])->name('users');
        Route::post('/users', [UsersController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UsersController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UsersController::class, 'destroy'])->name('users.destroy');
    });

    Route::middleware(['auth', 'permission:employees'])->group(function () {
        Route::get('/employees', [EmployeesController::class, 'index'])->name('employees');
        Route::post('/employees', [EmployeesController::class, 'store'])->name('employees.store');
        Route::patch('/employees/{employee}', [EmployeesController::class, 'update'])->name('employees.update');
        Route::delete('/employees/{employee}', [EmployeesController::class, 'destroy'])->name('employees.destroy');
    });

    Route::middleware(['auth', 'permission:roles'])->group(function () {
        Route::get('/roles', [RolesController::class, 'index'])->name('roles');
        Route::post('/roles', [RolesController::class, 'store'])->name('roles.store');
        Route::patch('/roles/{role}', [RolesController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RolesController::class, 'destroy'])->name('roles.destroy');
        Route::patch('/roles/{role}/permissions', [RolesController::class, 'updatePermissions'])->name('roles.permissions.update');
    });
    Route::middleware(['auth', 'permission:components'])->group(function () {
        Route::get('/components', [ComponentsController::class, 'index'])->name('components');
        Route::patch('/components/theme', [ComponentsController::class, 'updateTheme'])->name('components.theme.update');
        Route::patch('/components/active-theme', [ComponentsController::class, 'updateActiveTheme'])->name('components.active-theme.update');
        Route::patch('/components/landing-palette', [ComponentsController::class, 'updateLandingPalette'])->name('components.landing-palette.update');
        Route::patch('/components/branding', [ComponentsController::class, 'updateBranding'])->name('components.branding.update');
        Route::post('/components/branding/upload', [ComponentsController::class, 'uploadBrandingAsset'])->name('components.branding.upload');
    });
});

Route::middleware(['auth', 'module:inventory', 'permission:products'])->group(function () {
    Route::get('/products', [ProductsController::class, 'index'])->name('products');
    Route::post('/products', [ProductsController::class, 'store'])->name('products.store');
    Route::patch('/products/{product}', [ProductsController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductsController::class, 'destroy'])->name('products.destroy');
});

Route::middleware(['auth', 'module:services', 'permission:services'])->group(function () {
    Route::get('/services', [ServicesController::class, 'index'])->name('services');
    Route::post('/services', [ServicesController::class, 'store'])->name('services.store');
    Route::patch('/services/{service}', [ServicesController::class, 'update'])->name('services.update');
    Route::delete('/services/{service}', [ServicesController::class, 'destroy'])->name('services.destroy');
});

Route::middleware(['auth', 'module:appointments', 'permission:whatsapp.calendar'])->group(function () {
    Route::get('/whatsapp/calendar', [WhatsappCalendarController::class, 'index'])->name('whatsapp.calendar');
    Route::get('/whatsapp/calendar/{instance}', [WhatsappCalendarController::class, 'show'])->name('whatsapp.calendar.show');
    Route::post('/whatsapp/calendar/{instance}/sync', [WhatsappCalendarController::class, 'sync'])->name('whatsapp.calendar.sync');
    Route::delete('/whatsapp/calendar/{instance}/{appointment}', [WhatsappCalendarController::class, 'destroy'])->name('whatsapp.calendar.destroy');
});

Route::middleware(['auth', 'module:whatsapp', 'permission:whatsapp'])->group(function () {
    Route::middleware(['permission:whatsapp.instances'])->group(function () {
        Route::get('/whatsapp/instances', [WhatsappInstancesController::class, 'index'])->name('whatsapp.instances');
        Route::post('/whatsapp/instances', [WhatsappInstancesController::class, 'store'])->name('whatsapp.instances.store');
        Route::match(['patch', 'post'], '/whatsapp/instances/{instance}', [WhatsappInstancesController::class, 'update'])->name('whatsapp.instances.update');
        Route::post('/whatsapp/instances/{instance}/duplicate', [WhatsappInstancesController::class, 'duplicate'])->name('whatsapp.instances.duplicate');
        Route::delete('/whatsapp/instances/{instance}', [WhatsappInstancesController::class, 'destroy'])->name('whatsapp.instances.destroy');
        Route::post('/whatsapp/instances/{instance}/connect', [WhatsappInstancesController::class, 'connect'])->name('whatsapp.instances.connect');
    });

    Route::middleware(['permission:whatsapp.conversations'])->group(function () {
        Route::get('/whatsapp/conversations', [WhatsappConversationsController::class, 'index'])->name('whatsapp.conversations');
        Route::get('/whatsapp/conversations/{instance}', [WhatsappConversationsController::class, 'show'])->name('whatsapp.conversations.show');
        Route::delete('/whatsapp/conversations/{instance}/messages', [WhatsappConversationsController::class, 'destroyInstanceMessages'])->name('whatsapp.conversations.clear');
        Route::delete('/whatsapp/conversations/{instance}/{phone}', [WhatsappConversationsController::class, 'destroyThread'])->name('whatsapp.conversations.thread.destroy');
        Route::post('/whatsapp/conversations/{instance}/{phone}/resume-bot', [WhatsappConversationsController::class, 'resumeBot'])->name('whatsapp.conversations.resume');
    });

    Route::middleware(['permission:whatsapp.send'])->group(function () {
        Route::get('/whatsapp/send', [WhatsappMessagesController::class, 'index'])->name('whatsapp.send');
        Route::post('/whatsapp/send', [WhatsappMessagesController::class, 'store'])->name('whatsapp.send.store');
    });
});

Route::middleware(['auth'])->group(function () {
    Route::post('/theme', [ComponentsController::class, 'updateActiveTheme'])->name('theme.update');

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/feed', [NotificationsController::class, 'feed'])->name('feed');
        Route::patch('/{notification}/read', [NotificationsController::class, 'markAsRead'])->name('read');
        Route::post('/mark-all-read', [NotificationsController::class, 'markAllRead'])->name('mark-all-read');
    });
});

require __DIR__.'/auth.php';
