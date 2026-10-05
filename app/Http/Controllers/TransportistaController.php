<?php

namespace App\Http\Controllers;

use App\Models\Transportista;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransportistaController extends Controller
{
    public function index(Request $request, $emisorId): JsonResponse
    {
        $q = Transportista::where('emisor_id', $emisorId);

        if ($request->has('search')) {
            $search = $request->get('search');
            $q->where(function ($query) use ($search) {
                $query->where('identificacion', 'like', "%{$search}%")
                      ->orWhere('razon_social', 'like', "%{$search}%");
            });
        }

        $transportistas = $q->orderBy('razon_social')
            ->paginate($request->get('per_page', 15));
            
        return response()->json($transportistas);
    }

    public function store(Request $request, $emisorId): JsonResponse
    {
        $data = $request->validate([
            'tipo_identificacion' => ['required', 'string', 'max:10'],
            'identificacion' => ['required', 'string', 'max:20'],
            'razon_social' => ['required', 'string', 'max:300'],
            'email' => ['nullable', 'email'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'placa_vehiculo' => ['nullable', 'string', 'max:20'],
        ]);

        $data['emisor_id'] = $emisorId;
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $transportista = Transportista::create($data);

        return response()->json($transportista, 201);
    }

    public function show($emisorId, $id): JsonResponse
    {
        $transportista = Transportista::where('emisor_id', $emisorId)->findOrFail($id);
        return response()->json($transportista);
    }

    public function update(Request $request, $emisorId, $id): JsonResponse
    {
        $transportista = Transportista::where('emisor_id', $emisorId)->findOrFail($id);

        $data = $request->validate([
            'tipo_identificacion' => ['required', 'string', 'max:10'],
            'identificacion' => ['required', 'string', 'max:20'],
            'razon_social' => ['required', 'string', 'max:300'],
            'email' => ['nullable', 'email'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'placa_vehiculo' => ['nullable', 'string', 'max:20'],
        ]);

        $data['updated_by'] = Auth::id();

        $transportista->update($data);

        return response()->json($transportista);
    }

    public function destroy($emisorId, $id): JsonResponse
    {
        $transportista = Transportista::where('emisor_id', $emisorId)->findOrFail($id);
        $transportista->delete();
        return response()->json(null, 204);
    }
}
