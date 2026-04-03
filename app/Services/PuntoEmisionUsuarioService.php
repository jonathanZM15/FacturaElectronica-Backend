<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;

class PuntoEmisionUsuarioService
{
    /**
     * Retorna un mapa punto_id => usuario (slim) para los puntos indicados.
     *
     * La asignación real en este proyecto se guarda en users.puntos_emision_ids,
     * no necesariamente en puntos_emision.user_id.
     */
    public function mapUsuariosPorPuntoIds(int $companyId, array $puntoIds, ?int $excludeUserId = null): array
    {
        $ids = $this->normalizeIntIds($puntoIds);
        if (empty($ids)) {
            return [];
        }

        $idSet = array_fill_keys($ids, true);

        $query = User::query()
            ->where('emisor_id', $companyId)
            ->whereNotNull('puntos_emision_ids');

        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        // Traer solo lo necesario + puntos_emision_ids para armar el mapa.
        $users = $query->get(['id', 'username', 'role', 'nombres', 'apellidos', 'puntos_emision_ids']);

        $map = [];
        foreach ($users as $user) {
            $raw = $user->puntos_emision_ids;

            // Cast json suele entregar array, pero soportamos strings legacy.
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_string($decoded)) {
                    $decoded = json_decode($decoded, true);
                }
                $raw = $decoded;
            }

            $puntos = is_array($raw) ? $raw : [];
            foreach ($puntos as $pid) {
                if (!is_numeric($pid)) {
                    continue;
                }
                $pid = (int) $pid;
                if ($pid <= 0) {
                    continue;
                }
                if (!isset($idSet[$pid])) {
                    continue;
                }
                if (isset($map[$pid])) {
                    // En teoría no debería haber duplicados (punto debe estar LIBRE para asignar),
                    // pero si los hay nos quedamos con el primero.
                    continue;
                }

                $roleValue = $user->role instanceof UserRole ? $user->role->value : (string) ($user->role ?? '');

                $map[$pid] = [
                    'id' => $user->id,
                    'username' => $user->username,
                    'role' => $roleValue,
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos,
                ];
            }
        }

        return $map;
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
