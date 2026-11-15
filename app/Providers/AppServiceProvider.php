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
        if ($this->app->isProduction()) {
            config(['app.debug' => false]);
        }
        \Illuminate\Support\Facades\RateLimiter::for('auth-login', static function (\Illuminate\Http\Request $request) {
            $value = $request->input('email');
            $identifier = hash('sha256', mb_strtolower(trim(is_string($value) ? $value : '')));
            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by('login-ip:'.$request->ip()),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by('login-user:'.$identifier.':'.$request->ip()),
            ];
        });
        \Illuminate\Support\Facades\RateLimiter::for('auth-email', static function (\Illuminate\Http\Request $request) {
            $value = $request->input('email', $request->input('new_email', ''));
            $email = hash('sha256', mb_strtolower(trim(is_string($value) ? $value : '')));
            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by('email-ip:'.$request->ip()),
                \Illuminate\Cache\RateLimiting\Limit::perHour(10)->by('email-address:'.$email),
            ];
        });
        \Illuminate\Support\Facades\RateLimiter::for('auth-link', static fn (\Illuminate\Http\Request $request) =>
            \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($request->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('account-sensitive', static fn (\Illuminate\Http\Request $request) =>
            \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by((string) $request->user()?->id.':'.$request->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('account-check', static fn (\Illuminate\Http\Request $request) =>
            \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by((string) $request->user()?->id.':'.$request->ip()));

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
            // No hay emisor válido — devolver el valor original para que el controlador lo maneje
            return (string) $value;
        });
    }
}
