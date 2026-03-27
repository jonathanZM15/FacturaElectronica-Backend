<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Suscripcion;
use App\Observers\SuscripcionObserver;

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
        /**
         * Registrar observadores de modelos
         * El SuscripcionObserver resetea last_activity_at cuando se renueva una suscripción
         */
        Suscripcion::observe(SuscripcionObserver::class);
    }
}
