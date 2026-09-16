<?php

namespace App\Traits;

use App\Models\Company;

trait ResolvesEmisor
{
    /**
     * Resuelve el ID del emisor real en caso de que se pase un ID inválido
     * o fallback (como 1) por usuarios con rol de administrador sin emisor asignado.
     */
    protected function resolveEmisorId($emisorId): ?int
    {
        if (!empty($emisorId)) {
            $emisor = Company::find($emisorId);
            if ($emisor) {
                return (int) $emisor->id;
            }
        }

        // Si el usuario autenticado tiene emisor_id asignado
        if (auth()->check() && auth()->user()->emisor_id) {
            $emisor = Company::find(auth()->user()->emisor_id);
            if ($emisor) {
                return (int) $emisor->id;
            }
        }

        // Fallback al primer emisor registrado en la base de datos
        $first = Company::first();
        return $first ? (int) $first->id : null;
    }
}
