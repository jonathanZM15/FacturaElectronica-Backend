<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PosTurno;
use App\Models\PuntoEmision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PosTurnoController extends Controller
{
    public function active(Request $request, string $emisorId): JsonResponse
    {
        $usuarioId = $request->user()->id;

        $turno = PosTurno::with('puntoEmision.establecimiento')
            ->whereHas('puntoEmision.establecimiento', function($q) use ($emisorId) {
                $q->where('emisor_id', $emisorId);
            })
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'Abierto')
            ->first();

        return response()->json(['data' => $turno]);
    }

    public function aperturar(Request $request, string $emisorId): JsonResponse
    {
        $usuarioId = $request->user()->id;

        $request->validate([
            'punto_emision_id' => 'required|integer',
            'saldo_inicial' => 'required|numeric|min:0'
        ]);

        $punto = PuntoEmision::whereHas('establecimiento', function($q) use ($emisorId) {
                $q->where('emisor_id', $emisorId);
            })
            ->where('activo', true)
            ->findOrFail($request->punto_emision_id);

        // Check if user already has an active shift in this emisor
        $existingUserTurn = PosTurno::whereHas('puntoEmision.establecimiento', function($q) use ($emisorId) {
                $q->where('emisor_id', $emisorId);
            })
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'Abierto')
            ->first();

        if ($existingUserTurn) {
            return response()->json(['error' => 'Ya tienes un turno abierto en el Punto: ' . $existingUserTurn->puntoEmision->codigo], 400);
        }

        // Check if the punto is already opened by someone else
        $existingPuntoTurn = PosTurno::where('punto_emision_id', $punto->id)
            ->where('estado', 'Abierto')
            ->first();

        if ($existingPuntoTurn) {
            return response()->json(['error' => 'Este Punto de Emisión ya está en uso por el usuario: ' . $existingPuntoTurn->usuario->name], 400);
        }

        $turno = PosTurno::create([
            'punto_emision_id' => $punto->id,
            'usuario_id' => $usuarioId,
            'fecha_apertura' => Carbon::now(),
            'saldo_inicial' => $request->saldo_inicial,
            'estado' => 'Abierto',
            'observaciones' => $request->observaciones
        ]);

        return response()->json([
            'message' => 'Turno aperturado con éxito',
            'data' => $turno->load('puntoEmision')
        ], 201);
    }

    public function cerrar(Request $request, string $emisorId, int $turnoId): JsonResponse
    {
        $usuarioId = $request->user()->id;

        $turno = PosTurno::whereHas('puntoEmision.establecimiento', function($q) use ($emisorId) {
                $q->where('emisor_id', $emisorId);
            })
            ->where('id', $turnoId)
            ->where('estado', 'Abierto')
            ->firstOrFail();

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
            'message' => 'Turno cerrado con éxito',
            'data' => $turno
        ]);
    }
}
