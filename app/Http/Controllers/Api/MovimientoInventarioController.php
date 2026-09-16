<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransferenciaRequest;
use App\Http\Requests\StoreAjusteRequest;
use App\Services\MovimientoInventarioService;
use App\Models\Bodega;
use App\Models\RegistroOperativoDetalle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Exceptions\InvalidWarehouseOperationException;

class MovimientoInventarioController extends Controller
{
    use \App\Traits\ResolvesEmisor;

    protected $movimientoService;

    public function __construct(MovimientoInventarioService $movimientoService)
    {
        $this->movimientoService = $movimientoService;
    }

    public function inventarioInicial(\App\Http\Requests\InventarioInicialRequest $request, string $emisorId): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);
        try {
            $bodega = Bodega::with('establecimiento')->whereHas('establecimiento', function($q) use ($resolvedId) {
                $q->where('emisor_id', $resolvedId);
            })->findOrFail($request->bodega_id);

            $usuarioId = $request->user()->id;

            $movimiento = $this->movimientoService->inventarioInicial(
                $bodega, $request->detalles, $request->observacion ?? 'Inventario Inicial', $usuarioId
            );

            return response()->json(['message' => 'Inventario inicial registrado con éxito', 'data' => $movimiento], 201);
            
        } catch (InvalidWarehouseOperationException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al procesar inventario inicial: ' . $e->getMessage()], 500);
        }
    }

    public function reacondicionar(\App\Http\Requests\TransferenciaRequest $request, string $emisorId): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);
        try {
            $origen = Bodega::with('establecimiento')->whereHas('establecimiento', function($q) use ($resolvedId) {
                $q->where('emisor_id', $resolvedId);
            })->findOrFail($request->bodega_origen_id);
            
            $destino = Bodega::with('establecimiento')->whereHas('establecimiento', function($q) use ($resolvedId) {
                $q->where('emisor_id', $resolvedId);
            })->findOrFail($request->bodega_destino_id);

            $usuarioId = $request->user()->id;

            $movimiento = $this->movimientoService->transferirReacondicionado(
                $origen, $destino, $request->detalles, $request->observacion ?? '', $usuarioId
            );

            return response()->json(['message' => 'Reacondicionamiento realizado con éxito', 'data' => $movimiento], 201);
            
        } catch (InvalidWarehouseOperationException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al procesar el reacondicionamiento: ' . $e->getMessage()], 500);
        }
    }

    public function ajustar(StoreAjusteRequest $request, string $emisorId): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);
        try {
            $bodega = Bodega::with('establecimiento')->whereHas('establecimiento', function($q) use ($resolvedId) {
                $q->where('emisor_id', $resolvedId);
            })->findOrFail($request->bodega_id);

            $usuarioId = $request->user()->id;

            // Compatibilidad temporal con el payload actual de frontend
            $tipo = $request->tipo;
            if (str_contains($tipo, 'POSITIVO')) {
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
        $resolvedId = $this->resolveEmisorId($emisorId);
        
        $historial = RegistroOperativoDetalle::with([
            'movimiento.origen', 
            'movimiento.destino', 
            'producto'
        ])
        ->whereHas('movimiento', function ($q) use ($resolvedId) {
            $q->where('emisor_id', $resolvedId);
            $q->where('estado', 'CONFIRMADO');
        });

        if ($request->has('producto_id')) {
            $historial->where('producto_id', $request->producto_id);
        }

        if ($request->has('bodega_id')) {
            $historial->whereHas('movimiento', function($q) use ($request) {
                $q->where('bodega_origen_id', $request->bodega_id)
                  ->orWhere('bodega_destino_id', $request->bodega_id);
            });
        }

        return response()->json($historial->orderBy('created_at', 'desc')->paginate(20));
    }
}




