<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Suscripcion;
use App\Models\User;
use App\Observers\SuscripcionObserver;
use App\Observers\UserObserver;

use Illuminate\Database\Eloquent\Relations\Relation;

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
        Relation::morphMap([
            'RegistroOperativo' => \App\Models\RegistroOperativoMovimiento::class,
            'Comprobante' => \App\Models\Comprobante::class,
            // Aquí irán añadiéndose los que sean necesarios
        ]);

        /**
         * Registrar observadores de modelos
         * - SuscripcionObserver: resetea last_activity_at cuando se renueva una suscripción
         * - UserObserver: envía correos automáticamente cuando cambia el estado del usuario
         */
        Suscripcion::observe(SuscripcionObserver::class);
        User::observe(UserObserver::class);

        \Illuminate\Support\Facades\Route::bind('emisorId', function ($value) {
            $company = \App\Models\Company::find($value);
            if ($company) {
                return (string) $company->id;
            }
            $user = auth()->user();
            if ($user && $user->emisor_id) {
                $userCompany = \App\Models\Company::find($user->emisor_id);
                if ($userCompany) {
                    return (string) $userCompany->id;
                }
            }
            $first = \App\Models\Company::first();
            return $first ? (string) $first->id : (string) $value;
        });
    }
}
