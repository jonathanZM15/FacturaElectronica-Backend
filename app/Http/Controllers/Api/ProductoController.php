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
    use \App\Traits\ResolvesEmisor;

    public function index(string $emisorId): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);
        \Illuminate\Support\Facades\Log::info("ProductoController::index called with emisorId: " . $emisorId . " resolved to: " . $resolvedId);
        $productos = Producto::with(['categoria', 'bodegas'])->where('emisor_id', $resolvedId)->get();
        \Illuminate\Support\Facades\Log::info("ProductoController::index returning count: " . $productos->count());
        return response()->json(['data' => $productos]);
    }

    public function store(StoreProductoRequest $request, string $emisorId, ProductoStockService $stockService): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);
        try {
            $producto = DB::transaction(function () use ($request, $resolvedId, $stockService) {
                $data = $request->validated();
                $data['emisor_id'] = $resolvedId;
                
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
        $resolvedId = $this->resolveEmisorId($emisorId);
        $producto = Producto::with(['categoria', 'bodegas'])->where('emisor_id', $resolvedId)->findOrFail($id);
        return response()->json(['data' => $producto]);
    }

    public function update(UpdateProductoRequest $request, string $emisorId, string $id): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);
        $producto = Producto::where('emisor_id', $resolvedId)->findOrFail($id);
        $producto->update($request->validated());

        return response()->json([
            'message' => 'Producto actualizado',
            'data' => $producto->load('categoria')
        ]);
    }

    public function destroy(string $emisorId, string $id): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);
        $producto = Producto::where('emisor_id', $resolvedId)->findOrFail($id);
        $producto->delete();

        return response()->json(['message' => 'Producto eliminado exitosamente']);
    }

    public function stockDisponible(string $emisorId, string $id): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);
        $producto = Producto::where('emisor_id', $resolvedId)->findOrFail($id);

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
