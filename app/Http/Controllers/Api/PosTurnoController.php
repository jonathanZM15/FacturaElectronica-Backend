<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\PosTurno;
use App\Models\Establecimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PosTurnoController extends Controller
{
    private function getEstablecimientosDelEmisor(string $emisorId)
    {
        return Establecimiento::where('emisor_id', $emisorId)->pluck('id');
    }

    public function active(Request $request, string $emisorId): JsonResponse
    {
        $usuarioId = $request->user()->id;
        $establecimientoIds = $this->getEstablecimientosDelEmisor($emisorId);

        $turno = PosTurno::with('caja.establecimiento')
            ->whereHas('caja', function($q) use ($establecimientoIds) {
                $q->whereIn('establecimiento_id', $establecimientoIds);
            })
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'Abierto')
            ->first();

        return response()->json(['data' => $turno]);
    }

    public function aperturar(Request $request, string $emisorId): JsonResponse
    {
        $establecimientoIds = $this->getEstablecimientosDelEmisor($emisorId);
        $usuarioId = $request->user()->id;

        $request->validate([
            'caja_id' => 'required|integer',
            'saldo_inicial' => 'required|numeric|min:0'
        ]);

        $caja = Caja::whereIn('establecimiento_id', $establecimientoIds)
            ->where('activa', true)
            ->findOrFail($request->caja_id);

        // Check if user already has an active shift in this emisor
        $existingUserTurn = PosTurno::whereHas('caja', function($q) use ($establecimientoIds) {
                $q->whereIn('establecimiento_id', $establecimientoIds);
            })
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'Abierto')
            ->first();

        if ($existingUserTurn) {
            return response()->json(['error' => 'Ya tienes un turno abierto en la caja: ' . $existingUserTurn->caja->nombre], 400);
        }

        // Check if the caja is already opened by someone else
        $existingCajaTurn = PosTurno::where('caja_id', $caja->id)
            ->where('estado', 'Abierto')
            ->first();

        if ($existingCajaTurn) {
            return response()->json(['error' => 'La caja seleccionada ya está abierta por el usuario: ' . $existingCajaTurn->usuario->name], 400);
        }

        $turno = PosTurno::create([
            'caja_id' => $caja->id,
            'usuario_id' => $usuarioId,
            'fecha_apertura' => Carbon::now(),
            'saldo_inicial' => $request->saldo_inicial,
            'estado' => 'Abierto',
            'observaciones' => $request->observaciones
        ]);

        return response()->json([
            'message' => 'Caja aperturada con éxito',
            'data' => $turno->load('caja')
        ], 201);
    }

    public function cerrar(Request $request, string $emisorId, int $turnoId): JsonResponse
    {
        $establecimientoIds = $this->getEstablecimientosDelEmisor($emisorId);
        $usuarioId = $request->user()->id;

        $turno = PosTurno::whereHas('caja', function($q) use ($establecimientoIds) {
                $q->whereIn('establecimiento_id', $establecimientoIds);
            })
            ->where('id', $turnoId)
            ->where('estado', 'Abierto')
            ->firstOrFail();

        // En un escenario estricto, solo el usuario que abrió o un admin puede cerrar
        if ($turno->usuario_id !== $usuarioId && $request->user()->role !== 'administrador') {
            return response()->json(['error' => 'No tienes permisos para cerrar este turno.'], 403);
        }

        $request->validate([
            'saldo_final' => 'required|numeric|min:0'
        ]);

        $turno->update([
            'fecha_cierre' => Carbon::now(),
            'saldo_final' => $request->saldo_final,
            'estado' => 'Cerrado',
            'observaciones' => $request->observaciones ? ($turno->observaciones . "\nCierre: " . $request->observaciones) : $turno->observaciones
        ]);

        return response()->json([
            'message' => 'Caja cerrada con éxito',
            'data' => $turno
        ]);
    }
}
