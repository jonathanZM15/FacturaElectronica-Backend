<?php

namespace App\Services;

use App\Models\PuntoEmision;
use App\Models\User;

class PuntoEmisionDisponibilidadService
{
    public const LIBRE = 'LIBRE';
    public const OCUPADO = 'OCUPADO';

    /**
     * Marca puntos como OCUPADO (cuando se asocian a un usuario).
     */
    public function markOcupado(int $companyId, array $puntoIds): void
    {
        $ids = $this->normalizeIntIds($puntoIds);
        if (empty($ids)) {
            return;
        }

        PuntoEmision::where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->update(['estado_disponibilidad' => self::OCUPADO]);
    }

    /**
     * Recalcula LIBRE/OCUPADO para puntos específicos.
     *
     * Útil al desasociar puntos de un usuario: si nadie más lo tiene, queda LIBRE.
     */
    public function recalculate(int $companyId, array $puntoIds, ?int $excludeUserId = null): void
    {
        $ids = $this->normalizeIntIds($puntoIds);
        if (empty($ids)) {
            return;
        }

        foreach ($ids as $puntoId) {
            $hasAnyAssignment = $this->hasAnyAssignment($companyId, $puntoId, $excludeUserId);
            PuntoEmision::where('company_id', $companyId)
                ->where('id', $puntoId)
                ->update([
                    'estado_disponibilidad' => $hasAnyAssignment ? self::OCUPADO : self::LIBRE,
                ]);
        }
    }

    private function hasAnyAssignment(int $companyId, int $puntoId, ?int $excludeUserId = null): bool
    {
        $query = User::query()
            ->where('emisor_id', $companyId)
            ->whereNotNull('puntos_emision_ids');

        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        $pid = (string) $puntoId;

        return $query
            ->where(function ($q) use ($puntoId, $pid) {
                // Caso normal: JSON real
                $q->whereJsonContains('puntos_emision_ids', $puntoId)
                    // Casos tolerantes: doble-serialización o formatos previos
                    ->orWhere('puntos_emision_ids', 'like', '%[' . $pid . ',%')
                    ->orWhere('puntos_emision_ids', 'like', '%,' . $pid . ',%')
                    ->orWhere('puntos_emision_ids', 'like', '%,' . $pid . ']%')
                    ->orWhere('puntos_emision_ids', 'like', '%[' . $pid . ']%')
                    ->orWhere('puntos_emision_ids', 'like', '%"' . $pid . '"%');
            })
            ->exists();
    }

    private function normalizeIntIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            if (is_numeric($id)) {
                $out[] = (int) $id;
            }
        }
        $out = array_values(array_unique($out));
        return array_values(array_filter($out, fn ($v) => $v > 0));
    }
}
