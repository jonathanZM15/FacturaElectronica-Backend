<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kardex;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class KardexController extends Controller
{
    use \App\Traits\ResolvesEmisor;

    public function index(Request $request, string $emisorId): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);

        // Solo traemos Kardex de las bodegas que pertenecen al Emisor actual
        $query = Kardex::with([
            'producto:id,codigo,nombre',
            'bodega:id,codigo,nombre,establecimiento_id',
            'bodega.establecimiento:id,codigo,nombre'
        ])->whereHas('bodega.establecimiento', function ($q) use ($resolvedId) {
            $q->where('emisor_id', $resolvedId);
        });

        // 1. Filtro por Producto
        if ($request->filled('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }

        // 2. Filtro por Bodega
        if ($request->filled('bodega_id')) {
            $query->where('bodega_id', $request->bodega_id);
        }

        // 3. Filtro por Establecimiento
        if ($request->filled('establecimiento_id')) {
            $query->whereHas('bodega', function ($q) use ($request) {
                $q->where('establecimiento_id', $request->establecimiento_id);
            });
        }

        // 4. Filtro por Tipo de Movimiento
        if ($request->filled('tipo_movimiento')) {
            $query->where('tipo_movimiento', $request->tipo_movimiento);
        }

        // 5. Filtro por Fechas
        if ($request->filled('fecha_inicio')) {
            $query->whereDate('fecha_hora', '>=', $request->fecha_inicio);
        }
        if ($request->filled('fecha_fin')) {
            $query->whereDate('fecha_hora', '<=', $request->fecha_fin);
        }

        // Ordenamos cronológicamente (los más recientes primero)
        $query->orderBy('fecha_hora', 'desc')->orderBy('id', 'desc');

        return response()->json($query->paginate(20));
    }
}
