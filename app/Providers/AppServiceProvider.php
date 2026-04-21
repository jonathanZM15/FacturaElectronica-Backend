<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Suscripcion;
use App\Models\User;
use App\Observers\SuscripcionObserver;
use App\Observers\UserObserver;

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
         * - SuscripcionObserver: resetea last_activity_at cuando se renueva una suscripción
         * - UserObserver: envía correos automáticamente cuando cambia el estado del usuario
         */
        Suscripcion::observe(SuscripcionObserver::class);
        User::observe(UserObserver::class);
    }
}
