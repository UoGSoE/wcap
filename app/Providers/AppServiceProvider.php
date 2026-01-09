<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
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
        Blade::if('admin', fn () => auth()->check() && auth()->user()->isAdmin());
        Blade::if('manager', fn () => auth()->check() && auth()->user()->isManager());
        Blade::if('adminOrManager', fn () => auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isManager()));
        Blade::if('servicesEnabled', fn () => config('wcap.services_enabled'));
    }
}
