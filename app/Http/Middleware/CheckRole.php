<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!auth('sanctum')->check()) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $user = auth('sanctum')->user();

        if (!in_array($user->role, $roles)) {
            \Log::warning("Acceso denegado por rol. Usuario: {$user->id}, Rol: {$user->role}, Roles requeridos: " . implode(', ', $roles), ['user_id' => $user->id]);
            return response()->json(['message' => 'No tienes permiso para acceder a este recurso'], 403);
        }

        return $next($request);
    }
}
