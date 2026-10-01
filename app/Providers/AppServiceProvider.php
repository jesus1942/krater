<?php

namespace Crater\Providers;

use Crater\Services\Access\AccessManager;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrapThree();
        $this->loadJsonTranslationsFrom(resource_path('assets/js/plugins'));
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // R1: la autoridad sale exclusivamente de asignaciones RBAC vigentes.
        $this->app->singleton(AccessManager::class);
    }
}
