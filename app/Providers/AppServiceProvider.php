<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use App\Models\ComponentThemeModel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\Tenancy\CompanyContext::class);

        $this->app->bind(
            \App\Services\Whatsapp\Contracts\MessengerGatewayInterface::class,
            \App\Services\Whatsapp\EvolutionApiClient::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production') || request()->header('x-forwarded-proto') === 'https') {
            URL::forceScheme('https');
        }

        Vite::prefetch(concurrency: 3);

        View::composer('app', function ($view) {
            $doc = ComponentThemeModel::first();
            $view->with('componentTheme', $doc?->styles ?? []);
            $view->with('activeTheme', $doc?->active_theme ?? 'dark');
        });
    }
}
