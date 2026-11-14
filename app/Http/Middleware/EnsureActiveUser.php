<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user || !$user->estaActivo()) {
            $user?->tokens()->delete();
            return response()->json(['message' => 'La cuenta no está activa. Inicia sesión con una cuenta habilitada.', 'code' => 'ACCOUNT_INACTIVE'], 403);
        }
        return $next($request);
    }
}
