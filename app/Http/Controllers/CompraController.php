<?php

namespace App\\Http\\Controllers;

use App\\Models\\Compra;
use Illuminate\\Http\\Request;
use Illuminate\\Http\\JsonResponse;

class CompraController extends Controller
{
    public function index(Request $request, $emisorId): JsonResponse
    {
        $q = Compra::with(['proveedor', 'detalles'])->where('emisor_id', $emisorId);

        // Filters
        if ($request->has('tipo_ingreso')) {
            $q->where('tipo_ingreso', $request->tipo_ingreso);
        }

        if ($request->has('fecha_inicio') && $request->has('fecha_fin')) {
            $q->whereBetween('fecha_emision', [$request->fecha_inicio, $request->fecha_fin]);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $q->where(function($query) use ($search) {
                $query->where('numero_comprobante', 'like', "%{$search}%")
                      ->orWhereHas('proveedor', function($pQuery) use ($search) {
                          $pQuery->where('razon_social', 'like', "%{$search}%")
                                 ->orWhere('identificacion', 'like', "%{$search}%");
                      });
            });
        }

        $compras = $q->orderBy('fecha_emision', 'desc')
                     ->orderBy('id', 'desc')
                     ->paginate($request->get('per_page', 15));

        return response()->json($compras);
    }
}
