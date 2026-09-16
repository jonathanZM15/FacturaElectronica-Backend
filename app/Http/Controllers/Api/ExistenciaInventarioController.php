<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\ProductoBodegaStock;
use App\Models\ExistenciaLote;
use App\Models\Serie;

class ExistenciaInventarioController extends Controller
{
    use \App\Traits\ResolvesEmisor;

    /**
     * Consulta general de existencias (stock consolidado por bodega/producto)
     */
    public function consolidado(Request $request, string $emisorId): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);
        $query = ProductoBodegaStock::with(['producto', 'bodega'])
            ->whereHas('bodega.establecimiento', function ($q) use ($resolvedId) {
                $q->where('emisor_id', $resolvedId);
            });

        if ($request->has('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }

        if ($request->has('bodega_id')) {
            $query->where('bodega_id', $request->bodega_id);
        }

        // Para evitar enviar registros con stock absoluto 0
        if ($request->boolean('solo_con_stock', true)) {
            $query->where('stock_fisico', '>', 0);
        }

        $existencias = $query->orderBy('bodega_id')->orderBy('producto_id')->paginate(50);
        return response()->json($existencias);
    }

    /**
     * Consulta detallada de existencias por Lote
     */
    public function lotes(Request $request, string $emisorId): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);
        $query = ExistenciaLote::with(['producto', 'bodega', 'lote'])
            ->whereHas('bodega.establecimiento', function ($q) use ($resolvedId) {
                $q->where('emisor_id', $resolvedId);
            });

        if ($request->has('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }

        if ($request->has('bodega_id')) {
            $query->where('bodega_id', $request->bodega_id);
        }

        if ($request->has('lote_id')) {
            $query->where('lote_id', $request->lote_id);
        }

        if ($request->has('numero_lote')) {
            $query->whereHas('lote', function ($q) use ($request) {
                $q->where('numero_lote', 'ilike', '%' . $request->numero_lote . '%');
            });
        }

        if ($request->boolean('solo_con_stock', true)) {
            $query->where('stock_fisico', '>', 0);
        }

        $lotes = $query->orderBy('bodega_id')->orderBy('producto_id')->paginate(50);
        return response()->json($lotes);
    }

    /**
     * Consulta detallada de Series (cada serie es una unidad 1 a 1)
     */
    public function series(Request $request, string $emisorId): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);
        $query = Serie::with(['producto', 'bodegaActual'])
            ->whereHas('producto', function ($q) use ($resolvedId) {
                $q->where('emisor_id', $resolvedId);
            });

        if ($request->has('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }

        if ($request->has('bodega_id')) {
            $query->where('bodega_actual_id', $request->bodega_id);
        }

        if ($request->has('numero_serie')) {
            $query->where('numero_serie', 'ilike', '%' . $request->numero_serie . '%');
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }
        
        // Si no mandamos estado, por defecto filtramos lo que esté en inventario si se pide
        if (!$request->has('estado') && $request->boolean('solo_en_bodega', false)) {
            $query->whereIn('estado', ['DISPONIBLE', 'BLOQUEADA', 'RESERVADA', 'DAÑADA', 'EN_TRANSITO']);
        }

        $series = $query->orderBy('producto_id')->paginate(50);
        return response()->json($series);
    }
}
