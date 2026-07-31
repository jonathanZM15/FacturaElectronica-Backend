<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransferenciaRequest;
use App\Http\Requests\StoreAjusteRequest;
use App\Services\MovimientoInventarioService;
use App\Models\Bodega;
use App\Models\MovimientoInventarioDetalle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Exceptions\InvalidWarehouseOperationException;

class MovimientoInventarioController extends Controller
{
    protected $movimientoService;

    public function __construct(MovimientoInventarioService $movimientoService)
    {
        $this->movimientoService = $movimientoService;
    }

    public function transferir(StoreTransferenciaRequest $request, string $emisorId): JsonResponse
    {
        try {
            $origen = Bodega::where('emisor_id', $emisorId)->findOrFail($request->bodega_origen_id);
            $destino = Bodega::where('emisor_id', $emisorId)->findOrFail($request->bodega_destino_id);
            $usuarioId = auth()->id() ?? 1; // Fallback para dev, idealmente auth()->id()

            // Si es salida de MERMAS, usa método de reacondicionado
            if ($origen->tipo->value === 'MERMAS') {
                $movimiento = $this->movimientoService->transferirReacondicionado(
                    $origen, $destino, $request->detalles, $request->observacion ?? '', $usuarioId
                );
            } else {
                $movimiento = $this->movimientoService->transferir(
                    $origen, $destino, $request->detalles, $request->observacion, $usuarioId
                );
            }

            return response()->json(['message' => 'Transferencia realizada con éxito', 'data' => $movimiento], 201);
            
        } catch (InvalidWarehouseOperationException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al procesar la transferencia: ' . $e->getMessage()], 500);
        }
    }

    public function ajustar(StoreAjusteRequest $request, string $emisorId): JsonResponse
    {
        try {
            $bodega = Bodega::where('emisor_id', $emisorId)->findOrFail($request->bodega_id);
            $usuarioId = auth()->id() ?? 1;

            if ($request->tipo === 'AJUSTE_POSITIVO') {
                $movimiento = $this->movimientoService->ajustarPositivo(
                    $bodega, $request->detalles, $request->observacion, $usuarioId
                );
            } else {
                $movimiento = $this->movimientoService->ajustarNegativo(
                    $bodega, $request->detalles, $request->observacion, $usuarioId
                );
            }

            return response()->json(['message' => 'Ajuste realizado con éxito', 'data' => $movimiento], 201);
            
        } catch (InvalidWarehouseOperationException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al procesar el ajuste: ' . $e->getMessage()], 500);
        }
    }

    public function kardex(Request $request, string $emisorId): JsonResponse
    {
        $query = MovimientoInventarioDetalle::with([
            'movimiento.origen', 
            'movimiento.destino', 
            'producto'
        ])
        ->whereHas('movimiento', function ($q) use ($emisorId) {
            $q->where('emisor_id', $emisorId);
        });

        if ($request->has('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }

        if ($request->has('bodega_id')) {
            $query->whereHas('movimiento', function($q) use ($request) {
                $q->where('bodega_origen_id', $request->bodega_id)
                  ->orWhere('bodega_destino_id', $request->bodega_id);
            });
        }

        $historial = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($historial);
    }
}
