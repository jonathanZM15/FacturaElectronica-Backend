<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductoBodegaStock;
use App\Models\Producto;
use App\Models\Bodega;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Enums\BaseComparacionStock;
use Illuminate\Validation\Rules\Enum;

class StockParametroController extends Controller
{
    // GET /emisores/{emisorId}/stock-parametros - List all with product and bodega names
    public function index(string $emisorId): JsonResponse
    {
        $parametros = ProductoBodegaStock::whereHas('producto', fn($q) => $q->where('emisor_id', $emisorId))
            ->with(['producto:id,nombre,codigo', 'bodega:id,nombre,tipo'])
            ->get();
        return response()->json(['data' => $parametros]);
    }

    // POST /emisores/{emisorId}/stock-parametros - Create/Update (upsert by producto_id+bodega_id)
    public function store(Request $request, string $emisorId): JsonResponse
    {
        $validated = $request->validate([
            'producto_id' => 'required|integer|exists:productos,id',
            'bodega_id' => 'required|integer|exists:bodegas,id',
            'stock_minimo' => 'nullable|numeric|min:0',
            'stock_maximo' => 'nullable|numeric|min:0',
            'base_comparacion' => ['nullable', new Enum(BaseComparacionStock::class)],
            'activo' => 'nullable|boolean',
            'observacion' => 'nullable|string|max:500',
        ]);

        // Validate min <= max if both are set
        if (isset($validated['stock_minimo']) && isset($validated['stock_maximo']) && $validated['stock_minimo'] > $validated['stock_maximo']) {
            return response()->json(['error' => 'El stock mínimo no puede ser mayor al stock máximo.'], 422);
        }

        $parametro = ProductoBodegaStock::updateOrCreate(
            ['producto_id' => $validated['producto_id'], 'bodega_id' => $validated['bodega_id']],
            collect($validated)->except(['producto_id', 'bodega_id'])->toArray()
        );

        $parametro->load(['producto:id,nombre,codigo', 'bodega:id,nombre,tipo']);

        return response()->json(['message' => 'Parámetro de stock guardado exitosamente', 'data' => $parametro], 201);
    }

    // DELETE /emisores/{emisorId}/stock-parametros/{id}
    public function destroy(string $emisorId, string $id): JsonResponse
    {
        $parametro = ProductoBodegaStock::whereHas('producto', fn($q) => $q->where('emisor_id', $emisorId))->findOrFail($id);
        $parametro->delete();
        return response()->json(['message' => 'Parámetro de stock eliminado exitosamente']);
    }
}
