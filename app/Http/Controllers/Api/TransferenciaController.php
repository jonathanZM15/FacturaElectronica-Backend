<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransferenciaRequest;
use App\Services\MovimientoInventarioService;
use App\Models\Bodega;
use Illuminate\Http\JsonResponse;

class TransferenciaController extends Controller
{
    protected $movimientoService;

    public function __construct(MovimientoInventarioService $movimientoService)
    {
        $this->movimientoService = $movimientoService;
    }

    public function store(TransferenciaRequest $request, int $emisorId): JsonResponse
    {
        $validated = $request->validated();
        
        $origen = Bodega::findOrFail($validated['bodega_origen_id']);
        $destino = Bodega::findOrFail($validated['bodega_destino_id']);
        $detalles = $validated['detalles'];
        $observacion = $validated['observacion'];
        $usuarioId = $request->user()->id;

        $movimiento = $this->movimientoService->transferir(
            $origen,
            $destino,
            $detalles,
            $observacion,
            $usuarioId
        );

        $tipoGrabado = $movimiento->tipo_movimiento instanceof \BackedEnum 
            ? $movimiento->tipo_movimiento->value 
            : $movimiento->tipo_movimiento;

        return response()->json([
            'message' => 'Transferencia registrada exitosamente',
            'movimiento' => $movimiento->numero,
            'tipo' => $tipoGrabado
        ], 201);
    }
}
