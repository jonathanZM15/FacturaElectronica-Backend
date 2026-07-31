<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use App\Services\ProductoStockService;
use App\Enums\TipoBodegaSalida;
use App\Enums\TipoBodega;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Exceptions\InvalidInventoryControlException;

class ProductoController extends Controller
{
    public function index(string $emisorId): JsonResponse
    {
        $productos = Producto::with(['categoria', 'bodegas'])->where('emisor_id', $emisorId)->get();
        return response()->json(['data' => $productos]);
    }

    public function store(StoreProductoRequest $request, string $emisorId, ProductoStockService $stockService): JsonResponse
    {
        try {
            $producto = DB::transaction(function () use ($request, $emisorId, $stockService) {
                $data = $request->validated();
                $data['emisor_id'] = $emisorId;
                
                $producto = Producto::create($data);

                if ($request->has('stock_inicial')) {
                    $stockService->procesarStockInicial($producto, $request->input('stock_inicial'));
                }

                return $producto;
            });

            return response()->json([
                'message' => 'Producto creado exitosamente',
                'data' => $producto->load('categoria')
            ], 201);
            
        } catch (InvalidInventoryControlException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Ocurrió un error al crear el producto: ' . $e->getMessage()], 500);
        }
    }

    public function show(string $emisorId, string $id): JsonResponse
    {
        $producto = Producto::with(['categoria', 'bodegas'])->where('emisor_id', $emisorId)->findOrFail($id);
        return response()->json(['data' => $producto]);
    }

    public function update(UpdateProductoRequest $request, string $emisorId, string $id): JsonResponse
    {
        $producto = Producto::where('emisor_id', $emisorId)->findOrFail($id);
        $producto->update($request->validated());

        return response()->json([
            'message' => 'Producto actualizado',
            'data' => $producto->load('categoria')
        ]);
    }

    public function destroy(string $emisorId, string $id): JsonResponse
    {
        $producto = Producto::where('emisor_id', $emisorId)->findOrFail($id);
        $producto->delete();

        return response()->json(['message' => 'Producto eliminado exitosamente']);
    }

    public function stockDisponible(string $emisorId, string $id): JsonResponse
    {
        $producto = Producto::where('emisor_id', $emisorId)->findOrFail($id);

        // Si es servicio o sin control, no hay stock que devolver
        if ($producto->tipo->value === 'SERVICIO' || $producto->tipo_control_inventario->value === 'SIN_CONTROL') {
            return response()->json([
                'message' => 'El producto no maneja stock',
                'stock_disponible' => null
            ]);
        }

        $tipoBodegaBuscada = $producto->tipo_bodega_salida === TipoBodegaSalida::VENTA 
            ? TipoBodega::VENTA 
            : TipoBodega::ALMACEN;

        $stockDisponible = $producto->bodegas()
            ->where('bodegas.tipo', $tipoBodegaBuscada)
            ->get()
            ->sum(fn ($bodega) => (float) ($bodega->pivot->stock_disponible ?? 0));

        return response()->json([
            'message' => 'Stock consultado exitosamente',
            'stock_disponible' => $stockDisponible,
            'tipo_bodega_priorizada' => $tipoBodegaBuscada->value
        ]);
    }
}
