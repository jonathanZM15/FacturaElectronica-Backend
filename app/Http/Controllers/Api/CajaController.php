<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\Establecimiento;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CajaController extends Controller
{
    private function getEstablecimientosDelEmisor(string $emisorId)
    {
        // Se asume que el usuario tiene acceso al emisor.
        return Establecimiento::where('emisor_id', $emisorId)->pluck('id');
    }

    public function index(Request $request, string $emisorId): JsonResponse
    {
        $establecimientoIds = $this->getEstablecimientosDelEmisor($emisorId);
        
        $cajas = Caja::with('establecimiento')
            ->whereIn('establecimiento_id', $establecimientoIds)
            ->when($request->query('establecimiento_id'), function($q) use ($request) {
                return $q->where('establecimiento_id', $request->query('establecimiento_id'));
            })
            ->get();
            
        return response()->json(['data' => $cajas]);
    }

    public function store(Request $request, string $emisorId): JsonResponse
    {
        $establecimientoIds = $this->getEstablecimientosDelEmisor($emisorId);
        
        $request->validate([
            'establecimiento_id' => 'required|integer|in:' . implode(',', $establecimientoIds->toArray()),
            'nombre' => 'required|string|max:100',
            'activa' => 'boolean'
        ]);

        $caja = Caja::create($request->all());

        return response()->json([
            'message' => 'Caja creada con éxito',
            'data' => $caja->load('establecimiento')
        ], 201);
    }

    public function update(Request $request, string $emisorId, int $id): JsonResponse
    {
        $establecimientoIds = $this->getEstablecimientosDelEmisor($emisorId);
        
        $caja = Caja::whereIn('establecimiento_id', $establecimientoIds)->findOrFail($id);

        $request->validate([
            'nombre' => 'string|max:100',
            'activa' => 'boolean'
        ]);

        $caja->update($request->all());

        return response()->json([
            'message' => 'Caja actualizada con éxito',
            'data' => $caja->load('establecimiento')
        ]);
    }

    public function destroy(string $emisorId, int $id): JsonResponse
    {
        $establecimientoIds = $this->getEstablecimientosDelEmisor($emisorId);
        $caja = Caja::whereIn('establecimiento_id', $establecimientoIds)->findOrFail($id);
        
        // Prevent deletion if there are turns
        if ($caja->turnos()->count() > 0) {
            return response()->json(['error' => 'No se puede eliminar la caja porque tiene historial de turnos.'], 400);
        }

        $caja->delete();

        return response()->json(['message' => 'Caja eliminada con éxito']);
    }
}
