<?php

use App\Http\Controllers\BranchesController;
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

use App\Http\Controllers\DashboardController;

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

use App\Http\Controllers\CompanyContextController;

Route::middleware('auth')->group(function () {
    Route::post('/companies/switch/{company}', [CompanyContextController::class, 'switch'])->name('companies.switch');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::post('/profile/banner', [ProfileController::class, 'updateBanner'])->name('profile.banner');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\CompaniesController;
use App\Http\Controllers\ComponentsController;
use App\Http\Controllers\EmployeesController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\ServicesController;

Route::middleware(['auth', 'permission:administration'])->group(function () {
    Route::get('/companies', [CompaniesController::class, 'index'])->middleware('permission:companies.view')->name('companies');
    Route::post('/companies', [CompaniesController::class, 'store'])->middleware('permission:companies.create')->name('companies.store');
    Route::patch('/companies/{company}', [CompaniesController::class, 'update'])->middleware('permission:companies.edit')->name('companies.update');
    Route::post('/companies/{company}/toggle-module', [CompaniesController::class, 'toggleModule'])->middleware('permission:companies.toggle_module')->name('companies.toggle-module');
    Route::post('/companies/{company}/branches', [BranchesController::class, 'store'])->middleware('permission:branches.create')->name('companies.branches.store');
    Route::patch('/companies/{company}/branches/{branch}', [BranchesController::class, 'update'])->middleware('permission:branches.edit')->name('companies.branches.update');
    Route::delete('/companies/{company}/branches/{branch}', [BranchesController::class, 'destroy'])->middleware('permission:branches.delete')->name('companies.branches.destroy');

    Route::post('/clients', [ClientsController::class, 'store'])->middleware('permission:companies.create')->name('clients.store');
    Route::patch('/clients/{client}', [ClientsController::class, 'update'])->middleware('permission:companies.edit')->name('clients.update');
    Route::delete('/clients/{client}', [ClientsController::class, 'destroy'])->middleware('permission:companies.edit')->name('clients.destroy');

    Route::middleware(['auth', 'permission:users'])->group(function () {
        Route::get('/users', [UsersController::class, 'index'])->middleware('permission:users.view')->name('users');
        Route::post('/users', [UsersController::class, 'store'])->middleware('permission:users.create')->name('users.store');
        Route::patch('/users/{user}', [UsersController::class, 'update'])->middleware('permission:users.edit')->name('users.update');
        Route::delete('/users/{user}', [UsersController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');
    });

    Route::middleware(['auth', 'permission:employees'])->group(function () {
        Route::get('/employees', [EmployeesController::class, 'index'])->middleware('permission:employees.view')->name('employees');
        Route::post('/employees', [EmployeesController::class, 'store'])->middleware('permission:employees.create')->name('employees.store');
        Route::patch('/employees/{employee}', [EmployeesController::class, 'update'])->middleware('permission:employees.edit')->name('employees.update');
        Route::delete('/employees/{employee}', [EmployeesController::class, 'destroy'])->middleware('permission:employees.delete')->name('employees.destroy');
    });
});

// Módulo exclusivo para el Super Administrador
Route::middleware(['auth', 'superadmin'])->group(function () {
    Route::get('/roles', [RolesController::class, 'index'])->name('roles');
    Route::post('/roles', [RolesController::class, 'store'])->name('roles.store');
    Route::patch('/roles/{role}', [RolesController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [RolesController::class, 'destroy'])->name('roles.destroy');
    Route::patch('/roles/{role}/permissions', [RolesController::class, 'updatePermissions'])->name('roles.permissions.update');
});

Route::middleware(['auth', 'module:inventory', 'permission:products'])->group(function () {
    Route::get('/products', [ProductsController::class, 'index'])->middleware('permission:products.view')->name('products');
    Route::post('/products', [ProductsController::class, 'store'])->middleware('permission:products.create')->name('products.store');
    Route::match(['patch', 'post'], '/products/{product}', [ProductsController::class, 'update'])->middleware('permission:products.edit')->name('products.update');
    Route::delete('/products/{product}', [ProductsController::class, 'destroy'])->middleware('permission:products.delete')->name('products.destroy');
});

Route::middleware(['auth', 'module:orders', 'permission:orders'])->group(function () {
    Route::get('/orders', [OrdersController::class, 'index'])->middleware('permission:orders.view')->name('orders');
    Route::post('/orders', [OrdersController::class, 'store'])->middleware('permission:orders.create')->name('orders.store');
    Route::patch('/orders/{order}', [OrdersController::class, 'update'])->middleware('permission:orders.edit')->name('orders.update');
    Route::delete('/orders/{order}', [OrdersController::class, 'destroy'])->middleware('permission:orders.delete')->name('orders.destroy');
});

Route::middleware(['auth', 'module:services', 'permission:services'])->group(function () {
    Route::get('/services', [ServicesController::class, 'index'])->middleware('permission:services.view')->name('services');
    Route::post('/services', [ServicesController::class, 'store'])->middleware('permission:services.create')->name('services.store');
    Route::match(['patch', 'post'], '/services/{service}', [ServicesController::class, 'update'])->middleware('permission:services.edit')->name('services.update');
    Route::delete('/services/{service}', [ServicesController::class, 'destroy'])->middleware('permission:services.delete')->name('services.destroy');
});

Route::middleware(['auth', 'module:appointments', 'permission:whatsapp.calendar'])->group(function () {
    Route::get('/whatsapp/calendar', [WhatsappCalendarController::class, 'index'])->middleware('permission:whatsapp.calendar.view')->name('whatsapp.calendar');
    Route::get('/whatsapp/calendar/{instance}', [WhatsappCalendarController::class, 'show'])->middleware('permission:whatsapp.calendar.view')->name('whatsapp.calendar.show');
    Route::post('/whatsapp/calendar/{instance}/sync', [WhatsappCalendarController::class, 'sync'])->middleware('permission:whatsapp.calendar.sync')->name('whatsapp.calendar.sync');
    Route::delete('/whatsapp/calendar/{instance}/{appointment}', [WhatsappCalendarController::class, 'destroy'])->middleware('permission:whatsapp.calendar.delete')->name('whatsapp.calendar.destroy');
});

Route::middleware(['auth', 'module:whatsapp', 'permission:whatsapp'])->group(function () {
    Route::middleware(['permission:whatsapp.instances'])->group(function () {
        Route::get('/whatsapp/instances', [WhatsappInstancesController::class, 'index'])->middleware('permission:whatsapp.instances.view')->name('whatsapp.instances');
        Route::post('/whatsapp/instances', [WhatsappInstancesController::class, 'store'])->middleware('permission:whatsapp.instances.create')->name('whatsapp.instances.store');
        Route::match(['patch', 'post'], '/whatsapp/instances/{instance}', [WhatsappInstancesController::class, 'update'])->middleware('permission:whatsapp.instances.edit')->name('whatsapp.instances.update');
        Route::post('/whatsapp/instances/{instance}/duplicate', [WhatsappInstancesController::class, 'duplicate'])->middleware('permission:whatsapp.instances.create')->name('whatsapp.instances.duplicate');
        Route::delete('/whatsapp/instances/{instance}', [WhatsappInstancesController::class, 'destroy'])->middleware('permission:whatsapp.instances.delete')->name('whatsapp.instances.destroy');
        Route::post('/whatsapp/instances/{instance}/connect', [WhatsappInstancesController::class, 'connect'])->middleware('permission:whatsapp.instances.connect')->name('whatsapp.instances.connect');
    });

    Route::middleware(['permission:whatsapp.conversations'])->group(function () {
        Route::get('/whatsapp/conversations', [WhatsappConversationsController::class, 'index'])->middleware('permission:whatsapp.conversations.view')->name('whatsapp.conversations');
        Route::get('/whatsapp/conversations/{instance}', [WhatsappConversationsController::class, 'show'])->middleware('permission:whatsapp.conversations.view')->name('whatsapp.conversations.show');
        Route::delete('/whatsapp/conversations/{instance}/messages', [WhatsappConversationsController::class, 'destroyInstanceMessages'])->middleware('permission:whatsapp.conversations.delete')->name('whatsapp.conversations.clear');
        Route::delete('/whatsapp/conversations/{instance}/{phone}', [WhatsappConversationsController::class, 'destroyThread'])->middleware('permission:whatsapp.conversations.delete')->name('whatsapp.conversations.thread.destroy');
        Route::post('/whatsapp/conversations/{instance}/{phone}/resume-bot', [WhatsappConversationsController::class, 'resumeBot'])->middleware('permission:whatsapp.conversations.resume_bot')->name('whatsapp.conversations.resume');
    });

    Route::middleware(['permission:whatsapp.send'])->group(function () {
        Route::get('/whatsapp/send', [WhatsappMessagesController::class, 'index'])->middleware('permission:whatsapp.send.view')->name('whatsapp.send');
        Route::post('/whatsapp/send', [WhatsappMessagesController::class, 'store'])->middleware('permission:whatsapp.send.execute')->name('whatsapp.send.store');
    });

    Route::middleware(['permission:whatsapp.chat-test'])->group(function () {
        Route::get('/whatsapp/chat-test', [\App\Http\Controllers\Whatsapp\ChatTestController::class, 'index'])->middleware('permission:whatsapp.chat-test.view')->name('whatsapp.chat-test');
        Route::post('/whatsapp/chat-test/send', [\App\Http\Controllers\Whatsapp\ChatTestController::class, 'send'])->middleware('permission:whatsapp.chat-test.send')->name('whatsapp.chat-test.send');
        Route::post('/whatsapp/chat-test/reset', [\App\Http\Controllers\Whatsapp\ChatTestController::class, 'reset'])->middleware('permission:whatsapp.chat-test.reset')->name('whatsapp.chat-test.reset');
        Route::get('/whatsapp/chat-test/history', [\App\Http\Controllers\Whatsapp\ChatTestController::class, 'history'])->middleware('permission:whatsapp.chat-test.view')->name('whatsapp.chat-test.history');
        Route::get('/whatsapp/chat-test/conversations', [\App\Http\Controllers\Whatsapp\ChatTestController::class, 'conversations'])->middleware('permission:whatsapp.chat-test.view')->name('whatsapp.chat-test.conversations');
    });
});

Route::middleware(['auth'])->get('/chat-test', fn () => redirect()->route('whatsapp.chat-test'))->name('chat-test');

Route::middleware(['auth'])->group(function () {
    Route::post('/theme', [ComponentsController::class, 'updateActiveTheme'])->name('theme.update');

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/feed', [NotificationsController::class, 'feed'])->name('feed');
        Route::patch('/{notification}/read', [NotificationsController::class, 'markAsRead'])->name('read');
        Route::post('/mark-all-read', [NotificationsController::class, 'markAllRead'])->name('mark-all-read');
    });
});

require __DIR__.'/auth.php';
