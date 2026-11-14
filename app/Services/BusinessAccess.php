<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\{Bodega, Company, Comprobante, Establecimiento, PuntoEmision, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class BusinessAccess
{
    public static function company(User $user, int $id): Company
    {
        $company = Company::findOrFail($id);
        $allowed = match ($user->role) {
            UserRole::ADMINISTRADOR => true,
            UserRole::DISTRIBUIDOR => (int) $company->created_by === (int) $user->id,
            default => $id > 0 && (int) $user->emisor_id === $id,
        };
        abort_unless($allowed, 403, 'No tienes acceso a esta empresa.');
        return $company;
    }

    /** null means company-wide; an empty array grants no operational scope. */
    public static function establishmentIds(User $user): ?array
    {
        if (!in_array($user->role, [UserRole::GERENTE, UserRole::CAJERO], true)) {
            return null;
        }
        $query = Establecimiento::where('emisor_id', $user->emisor_id);
        if ($user->role === UserRole::GERENTE) {
            $query->whereIn('id', $user->establecimientos_ids);
        } else {
            $query->whereHas('puntos_emision', fn ($q) => $q->whereIn('id', $user->puntos_emision_ids));
            if ($user->establecimientos_ids) {
                $query->whereIn('id', $user->establecimientos_ids);
            }
        }
        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public static function pointIds(User $user): ?array
    {
        $estIds = self::establishmentIds($user);
        if ($estIds === null) {
            return null;
        }
        $query = PuntoEmision::whereIn('establecimiento_id', $estIds);
        if ($user->role === UserRole::CAJERO || $user->puntos_emision_ids) {
            $query->whereIn('id', $user->puntos_emision_ids);
        }
        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public static function establishment(User $user, int $id, int $companyId): Establecimiento
    {
        self::company($user, $companyId);
        $est = Establecimiento::where('emisor_id', $companyId)->findOrFail($id);
        $ids = self::establishmentIds($user);
        abort_if($ids !== null && !in_array($id, $ids, true), 403, 'Sucursal no asignada.');
        return $est;
    }

    public static function point(User $user, int $id, int $companyId): PuntoEmision
    {
        $point = PuntoEmision::findOrFail($id);
        self::establishment($user, (int) $point->establecimiento_id, $companyId);
        $ids = self::pointIds($user);
        abort_if($ids !== null && !in_array($id, $ids, true), 403, 'Punto de emisión no asignado.');
        return $point;
    }

    public static function warehouse(User $user, int $id, int $companyId): Bodega
    {
        $warehouse = Bodega::findOrFail($id);
        self::establishment($user, (int) $warehouse->establecimiento_id, $companyId);
        return $warehouse;
    }

    public static function scopeWarehouses(Builder|Relation $query, User $user): Builder|Relation
    {
        $ids = self::establishmentIds($user);
        return $ids === null ? $query : $query->whereIn('establecimiento_id', $ids);
    }

    public static function scopeStock(Builder $query, User $user, string $relation = 'bodega'): Builder
    {
        return $query->whereHas($relation, fn ($q) => self::scopeWarehouses($q, $user));
    }

    public static function scopeInvoices(Builder $query, User $user): Builder
    {
        if ($user->role === UserRole::ADMINISTRADOR) {
            return $query;
        }
        if ($user->role === UserRole::DISTRIBUIDOR || !$user->emisor_id) {
            return $query->whereRaw('1 = 0');
        }
        $query->where('emisor_id', $user->emisor_id);
        if ($user->role === UserRole::GERENTE) {
            $query->whereIn('establecimiento_id', self::establishmentIds($user));
        } elseif ($user->role === UserRole::CAJERO) {
            $query->whereIn('punto_emision_id', self::pointIds($user));
        }
        return $query;
    }

    public static function invoice(User $user, Comprobante $invoice): void
    {
        abort_unless(self::scopeInvoices(Comprobante::query(), $user)->whereKey($invoice->id)->exists(), 403, 'Comprobante fuera de tu alcance.');
    }
}
