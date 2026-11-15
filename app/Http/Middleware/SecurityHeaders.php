<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        // Los detalles quedan en el registro del servidor, nunca en la respuesta pública.
        if ($request->is('api/*') && $response->getStatusCode() >= 500
            && !config('app.debug') && $response->getStatusCode() !== 501) {
            $response = response()->json(['message' => 'No se pudo completar la operación. Contacta al administrador.'], $response->getStatusCode());
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($request->is('api/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
        }
        if ($request->isSecure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        return $response;
    }
}
