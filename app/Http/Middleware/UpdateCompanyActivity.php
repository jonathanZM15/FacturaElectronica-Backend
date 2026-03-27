<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateCompanyActivity extends Middleware
{
    /**
     * Handle an incoming request.
     * Actualiza last_activity_at cada vez que un usuario accede a su empresa
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->emisor_id) {
            $company = auth()->user()->company;
            
            if ($company && !$company->is_marked_for_deletion) {
                $company->update([
                    'last_activity_at' => now()
                ]);
            }
        }

        return $next($request);
    }
}
