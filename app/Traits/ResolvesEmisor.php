<?php

namespace App\Traits;

use App\Models\Company;

trait ResolvesEmisor
{
    /**
     * Resuelve y valida el ID del emisor asegurando el aislamiento multi-tenant.
     * 
     * Reglas:
     * 1. Si el usuario es administrativo (administrador / distribuidor),
     *    puede gestionar el emisor especificado en $emisorId si existe.
     * 2. Si el usuario es de tenant (emisor, gerente, cajero),
     *    su emisor_id asignado DEBE coincidir con el $emisorId solicitado.
     *    Si intenta acceder a otro emisor en la URL, se bloquea con HTTP 403.
     */
    protected function resolveEmisorId($emisorId): ?int
    {
        $user = auth()->user();

        if (!$user) {
            abort(401, 'No autenticado.');
        }

        $targetEmisorId = (!empty($emisorId) && is_numeric($emisorId)) ? (int) $emisorId : null;

        // Usuarios con roles administrativos de plataforma
        $isAdministrativo = false;
        if (isset($user->role)) {
            if (is_object($user->role) && method_exists($user->role, 'esAdministrativo')) {
                $isAdministrativo = $user->role->esAdministrativo();
            } elseif (is_string($user->role)) {
                $isAdministrativo = in_array(strtolower($user->role), ['administrador', 'distribuidor']);
            }
        }

        if ($isAdministrativo) {
            if ($targetEmisorId) {
                $emisor = Company::find($targetEmisorId);
                if ($emisor) {
                    return (int) $emisor->id;
                }
                abort(404, 'Emisor no encontrado.');
            }
            if ($user->emisor_id) {
                return (int) $user->emisor_id;
            }
            abort(400, 'Debe especificar un emisor válido.');
        }

        // Usuarios de panel de cliente / tenant (emisor, gerente, cajero)
        $userEmisorId = $user->emisor_id ? (int) $user->emisor_id : null;
        if (!$userEmisorId) {
            abort(403, 'El usuario no tiene un emisor asignado.');
        }

        // Si la URL pide un emisor distinto al que pertenece el usuario -> 403
        if ($targetEmisorId && $targetEmisorId !== $userEmisorId) {
            abort(403, 'No tienes autorización para acceder a los datos de otro emisor.');
        }

        return $userEmisorId;
    }
}
