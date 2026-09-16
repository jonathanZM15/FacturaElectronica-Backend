<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DespachoSucursalRequest;
use App\Http\Requests\ConfirmacionRecepcionRequest;
use App\Services\MovimientoInventarioService;
use App\Models\Bodega;
use App\Models\RegistroOperativoMovimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Exceptions\InvalidWarehouseOperationException;

class TransferenciaSucursalController extends Controller
{
    use \App\Traits\ResolvesEmisor;

    protected $movimientoService;

    public function __construct(MovimientoInventarioService $movimientoService)
    {
        $this->movimientoService = $movimientoService;
    }

    /**
     * Lista los despachos MOV-07 de la empresa (útil para el flujo de descarga y recepción).
     */
    public function index(Request $request, int $emisorId): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);
        $query = RegistroOperativoMovimiento::with([
            'bodegaOrigen.establecimiento',
            'bodegaDestino.establecimiento',
            'usuario',
            'detalles.producto',
            'detalles.lotes.lote',
            'detalles.series.serie'
        ])
        ->where('emisor_id', $resolvedId)
        ->where('tipo_movimiento', \App\Enums\TipoMovimientoInventario::MOV_07_TRANSFERENCIA_SUCURSALES);

        if ($request->has('estado_operativo_recepcion') && $request->estado_operativo_recepcion !== '') {
            $query->where('estado_operativo_recepcion', $request->estado_operativo_recepcion);
        }

        if ($request->has('bodega_destino_id') && $request->bodega_destino_id !== '') {
            $query->where('bodega_destino_id', $request->bodega_destino_id);
        }

        if ($request->has('bodega_origen_id') && $request->bodega_origen_id !== '') {
            $query->where('bodega_origen_id', $request->bodega_origen_id);
        }

        $despachos = $query->orderBy('id', 'desc')->paginate(20);
        return response()->json($despachos);
    }

    /**
     * Muestra el detalle de un despacho específico para el formulario de recepción.
     */
    public function show(int $emisorId, int $movimientoId): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);
        $mov07 = RegistroOperativoMovimiento::with([
            'bodegaOrigen.establecimiento',
            'bodegaDestino.establecimiento',
            'usuario',
            'detalles.producto',
            'detalles.lotes.lote',
            'detalles.series.serie'
        ])
        ->where('emisor_id', $resolvedId)
        ->where('tipo_movimiento', \App\Enums\TipoMovimientoInventario::MOV_07_TRANSFERENCIA_SUCURSALES)
        ->findOrFail($movimientoId);

        return response()->json($mov07);
    }

    /**
     * MOV-07: Despacha mercadería desde bodega origen hacia bodega destino vía tránsito.
     * El estado queda en PENDIENTE_DE_DESCARGA hasta que el personal de destino ejecute descargar().
     */
    public function despachar(DespachoSucursalRequest $request, int $emisorId): JsonResponse
    {
        $validated = $request->validated();

        $origen = Bodega::findOrFail($validated['bodega_origen_id']);
        $destino = Bodega::findOrFail($validated['bodega_destino_id']);
        $detalles = $validated['detalles'];
        $observacion = $validated['observacion'];
        $usuarioId = $request->user()->id;

        $mov07 = $this->movimientoService->despacharTransferencia(
            $origen,
            $destino,
            $detalles,
            $observacion,
            $usuarioId
        );

        $tipoGrabado = $mov07->tipo_movimiento instanceof \BackedEnum
            ? $mov07->tipo_movimiento->value
            : $mov07->tipo_movimiento;

        $estadoOperativo = $mov07->estado_operativo_recepcion instanceof \BackedEnum
            ? $mov07->estado_operativo_recepcion->value
            : $mov07->estado_operativo_recepcion;

        return response()->json([
            'message'           => 'Despacho registrado exitosamente. Esperando descarga en destino.',
            'movimiento_id'     => $mov07->id,
            'movimiento_numero' => $mov07->numero,
            'tipo'              => $tipoGrabado,
            'estado_operativo'  => $estadoOperativo,
        ], 201);
    }

    /**
     * Evento de descarga: el personal de la bodega DESTINO confirma que el camión llegó físicamente.
     * Solo cambia el estado logístico (PENDIENTE_DE_DESCARGA -> DESCARGA_REGISTRADA).
     * NO toca inventario ni Kardex.
     */
    public function descargar(Request $request, int $emisorId, int $movimientoId): JsonResponse
    {
        $request->validate([
            'observacion' => ['required', 'string', 'max:255'],
        ]);

        $resolvedId = $this->resolveEmisorId($emisorId);
        $mov07 = RegistroOperativoMovimiento::where('emisor_id', $resolvedId)->findOrFail($movimientoId);
        $usuarioId = $request->user()->id;

        try {
            $this->movimientoService->registrarDescarga($mov07, $request->observacion, $usuarioId);
        } catch (InvalidWarehouseOperationException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $mov07->refresh();
        $estadoOperativo = $mov07->estado_operativo_recepcion instanceof \BackedEnum
            ? $mov07->estado_operativo_recepcion->value
            : $mov07->estado_operativo_recepcion;

        return response()->json([
            'message'          => 'Descarga registrada exitosamente.',
            'movimiento_id'    => $mov07->id,
            'estado_operativo' => $estadoOperativo,
        ], 200);
    }

    /**
     * MOV-08: Confirma la recepción física. Exige estado DESCARGA_REGISTRADA.
     * Mueve el stock de tránsito a destino, registra incidencias (faltante/dañado/sobrante).
     */
    public function recibir(ConfirmacionRecepcionRequest $request, int $emisorId, int $movimientoId): JsonResponse
    {
        $validated = $request->validated();

        $resolvedId = $this->resolveEmisorId($emisorId);
        $mov07 = RegistroOperativoMovimiento::where('emisor_id', $resolvedId)->findOrFail($movimientoId);

        $bodegaIncidencia = null;
        if (!empty($validated['bodega_incidencia_id'])) {
            $bodegaIncidencia = Bodega::findOrFail($validated['bodega_incidencia_id']);
        }

        $detalles   = $validated['detalles'];
        $observacion = $validated['observacion'];
        $usuarioId  = $request->user()->id;

        try {
            $mov08 = $this->movimientoService->confirmarRecepcion(
                $mov07,
                $detalles,
                $observacion,
                $usuarioId,
                $bodegaIncidencia
            );
        } catch (InvalidWarehouseOperationException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'           => 'Recepción confirmada exitosamente.',
            'movimiento_id'     => $mov08->id,
            'movimiento_numero' => $mov08->numero,
            'tipo'              => $mov08->tipo_movimiento instanceof \BackedEnum
                ? $mov08->tipo_movimiento->value
                : $mov08->tipo_movimiento,
        ], 201);
    }
}
