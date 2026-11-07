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
        $roleValue = $user->role instanceof \App\Enums\UserRole ? $user->role->value : (string) $user->role;

        if (!in_array($roleValue, $roles, true)) {
            \Log::warning("Acceso denegado por rol. Usuario: {$user->id}, Rol: {$roleValue}, Roles requeridos: " . implode(', ', $roles), ['user_id' => $user->id]);
            return response()->json(['message' => 'No tienes permiso para acceder a este recurso'], 403);
        }

        return $next($request);
    }
}
