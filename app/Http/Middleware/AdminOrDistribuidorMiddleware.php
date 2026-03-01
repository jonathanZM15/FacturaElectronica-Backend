<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class AdminOrDistribuidorMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            Log::warning('Acceso no autenticado a ruta admin/distribuidor');
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $userRole = auth()->user()->role;
        $roleValue = $userRole?->value;

        if (!in_array($roleValue, ['administrador', 'distribuidor'], true)) {
            Log::warning('Usuario sin permisos admin/distribuidor', [
                'user_id' => auth()->user()->id,
                'user_role' => $roleValue ?? 'null',
                'route' => $request->path(),
            ]);

            return response()->json([
                'message' => 'No tienes permiso para acceder a este recurso',
            ], 403);
        }

        return $next($request);
    }
}
