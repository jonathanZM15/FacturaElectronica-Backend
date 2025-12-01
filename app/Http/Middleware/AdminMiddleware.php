<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar que el usuario está autenticado
        if (!auth()->check()) {
            Log::warning('Acceso no autenticado a ruta admin');
            return response()->json([
                'message' => 'No autenticado'
            ], 401);
        }

        // Verificar que el usuario tiene rol de administrador
        $userRole = auth()->user()->role;
        if (!$userRole || $userRole->value !== 'administrador') {
            Log::warning('Usuario sin permisos admin', [
                'user_id' => auth()->user()->id,
                'user_role' => $userRole ? $userRole->value : 'null',
                'route' => $request->path()
            ]);
            return response()->json([
                'message' => 'No tienes permisos para acceder a este recurso'
            ], 403);
        }

        return $next($request);
    }
}
