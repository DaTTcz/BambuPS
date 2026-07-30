<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */

    public function boot(): void
    {
        // Pokud přichází request na port 8080 pro API, nepřesměrovávat na HTTPS
        if (request()->getPort() == 8080 && str_starts_with(request()->getPathInfo(), '/api')) {
            \URL::forceScheme('http');
        }
    }
}
