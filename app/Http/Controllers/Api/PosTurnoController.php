<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{PosTurno, PuntoEmision, User};
use App\Services\BusinessAccess;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;

class PosTurnoController extends Controller
{
    public function active(Request $request, string $emisorId): JsonResponse
    {
        BusinessAccess::company($request->user(), (int) $emisorId);
        $turn = PosTurno::with('puntoEmision.establecimiento')->where('usuario_id', $request->user()->id)->where('estado', 'Abierto')
            ->whereHas('puntoEmision.establecimiento', fn ($q) => $q->where('emisor_id', $emisorId))->first();
        if ($turn) BusinessAccess::point($request->user(), (int) $turn->punto_emision_id, (int) $emisorId);
        return response()->json(['data' => $turn]);
    }

    public function aperturar(Request $request, string $emisorId): JsonResponse
    {
        $data = $request->validate(['punto_emision_id' => ['required', 'integer'], 'saldo_inicial' => ['required', 'numeric', new \App\Rules\DecimalValue(2, '99999999.99')], 'observaciones' => ['nullable', 'string', 'max:1000']]);
        return DB::transaction(function () use ($request, $emisorId, $data) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $point = BusinessAccess::point($user, (int) $data['punto_emision_id'], (int) $emisorId);
            $point = PuntoEmision::whereKey($point->id)->lockForUpdate()->firstOrFail();
            abort_unless($point->estado === 'ACTIVO' && $point->establecimiento->estado === 'ABIERTO', 422, 'El punto y su sucursal deben estar activos.');
            if (PosTurno::where('usuario_id', $user->id)->where('estado', 'Abierto')->exists()) return response()->json(['error' => 'Ya tienes un turno abierto.'], 409);
            if (PosTurno::where('punto_emision_id', $point->id)->where('estado', 'Abierto')->exists()) return response()->json(['error' => 'El punto de emisión ya tiene un turno abierto.'], 409);
            $turn = PosTurno::create(['punto_emision_id' => $point->id, 'usuario_id' => $user->id, 'fecha_apertura' => now(), 'saldo_inicial' => $data['saldo_inicial'], 'estado' => 'Abierto', 'observaciones' => $data['observaciones'] ?? null]);
            return response()->json(['message' => 'Tu caja quedó abierta.', 'data' => $turn->load('puntoEmision.establecimiento')], 201);
        });
    }

    public function cerrar(Request $request, string $emisorId, int $turnoId): JsonResponse
    {
        $data = $request->validate(['saldo_final' => ['required', 'numeric', new \App\Rules\DecimalValue(2, '99999999.99')], 'observaciones' => ['nullable', 'string', 'max:1000']]);
        return DB::transaction(function () use ($request, $emisorId, $turnoId, $data) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $turn = PosTurno::whereKey($turnoId)->lockForUpdate()->firstOrFail();
            abort_unless((int) $turn->usuario_id === (int) $user->id, 403, 'Solo puedes cerrar tu propio turno.');
            BusinessAccess::point($user, (int) $turn->punto_emision_id, (int) $emisorId);
            abort_unless($turn->estado === 'Abierto', 409, 'El turno ya está cerrado.');
            $turn->update(['fecha_cierre' => now(), 'saldo_final' => $data['saldo_final'], 'estado' => 'Cerrado', 'observaciones' => trim(($turn->observaciones ?? '').(isset($data['observaciones']) ? "\nCierre: ".$data['observaciones'] : ''))]);
            return response()->json(['message' => 'Tu caja quedó cerrada.', 'data' => $turn]);
        });
    }
}
