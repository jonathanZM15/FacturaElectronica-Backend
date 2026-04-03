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
    public function markOcupado(int $companyId, int $userId, array $puntoIds): void
    {
        $ids = $this->normalizeIntIds($puntoIds);
        if (empty($ids)) {
            return;
        }

        PuntoEmision::where('emisor_id', $companyId)
            ->whereIn('id', $ids)
            ->update([
                'estado_disponibilidad' => self::OCUPADO,
                'user_id' => $userId,
            ]);
    }

    /**
     * Recalcula LIBRE/OCUPADO para puntos específicos (OPTIMIZADO - sin N+1).
     *
     * Útil al desasociar puntos de un usuario: si nadie más lo tiene, queda LIBRE.
     * 
     * Antes: foreach($ids) -> N queries (1 select + 1 update por punto)
     * Ahora: 1 query -> batch update
     */
    public function recalculate(int $companyId, array $puntoIds, ?int $excludeUserId = null): void
    {
        $ids = $this->normalizeIntIds($puntoIds);
        if (empty($ids)) {
            return;
        }

        // Obtener todos los puntos que TIENEN asignación en una sola query
        $query = User::query()
            ->where('emisor_id', $companyId)
            ->whereNotNull('puntos_emision_ids');

        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        // Extraer los IDs de puntos emision que están siendo utilizados
        $usedPuntoIds = [];
        foreach ($query->pluck('puntos_emision_ids') as $jsonArray) {
            if ($jsonArray) {
                $decoded = json_decode($jsonArray, true);
                if (is_array($decoded)) {
                    $usedPuntoIds = array_merge($usedPuntoIds, $decoded);
                }
            }
        }
        $usedPuntoIds = array_unique(array_filter($usedPuntoIds));

        // Identificar cuáles están libres y cuáles ocupados
        $libres = array_diff($ids, $usedPuntoIds);
        $ocupados = array_intersect($ids, $usedPuntoIds);

        // Batch update: todos los libres en UNA query
        if (!empty($libres)) {
            PuntoEmision::where('emisor_id', $companyId)
                ->whereIn('id', $libres)
                ->update([
                    'estado_disponibilidad' => self::LIBRE,
                    'user_id' => null,
                ]);
        }

        // Batch update: todos los ocupados en UNA query
        if (!empty($ocupados)) {
            PuntoEmision::where('emisor_id', $companyId)
                ->whereIn('id', $ocupados)
                ->update(['estado_disponibilidad' => self::OCUPADO]);
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

        // Primero intenta con JSON contains (más rápido con índices)
        // Si falla, cae a los LIKE como fallback
        return $query->whereJsonContains('puntos_emision_ids', $puntoId)->exists();
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
