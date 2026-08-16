<?php

namespace Crater\Providers;

use Crater\Services\Access\AccessManager;
use Crater\Services\Access\LegacyCompatibleAccessManager;
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
        // Durante la migracion RBAC, toda resolucion de AccessManager pasa por
        // la capa compatible que reconoce al superadmin historico en un unico
        // punto y conserva las reglas normales para el resto de los usuarios.
        $this->app->singleton(AccessManager::class, LegacyCompatibleAccessManager::class);
    }
}
