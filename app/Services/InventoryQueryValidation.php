<?php

namespace App\Services;

use App\Enums\{EstadoSerie, TipoMovimientoInventario};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryQueryValidation
{
    public static function validate(Request $request): void
    {
        $rules = [
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'fecha_inicio' => ['nullable', 'date_format:Y-m-d'],
            'fecha_fin' => ['nullable', 'date_format:Y-m-d'],
            'tipo_movimiento' => ['nullable', Rule::enum(TipoMovimientoInventario::class)],
            'estado' => ['nullable', Rule::enum(EstadoSerie::class)],
            'numero_lote' => ['nullable', 'string', 'max:100'],
            'numero_serie' => ['nullable', 'string', 'max:100'],
        ];
        foreach (['producto_id', 'bodega_id', 'establecimiento_id', 'motivo_id', 'lote_id'] as $id) {
            $rules[$id] = ['nullable', 'integer', 'min:1', 'max:2147483647'];
        }
        foreach (['solo_con_stock', 'solo_en_bodega'] as $flag) {
            $rules[$flag] = ['sometimes', Rule::in(['true', 'false', '1', '0', true, false])];
        }
        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) $rules['fecha_fin'][] = 'after_or_equal:fecha_inicio';
        $request->validate($rules);
    }
}
