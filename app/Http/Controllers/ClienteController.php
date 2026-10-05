<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClienteController extends Controller
{
    public function index(Request $request, $emisorId): JsonResponse
    {
        $q = Cliente::where('emisor_id', $emisorId);

        if ($request->has('search')) {
            $search = $request->get('search');
            $q->where(function ($query) use ($search) {
                $query->where('identificacion', 'like', "%{$search}%")
                      ->orWhere('razon_social', 'like', "%{$search}%");
            });
        }

        $clientes = $q->orderBy('razon_social')
                      ->paginate($request->get('per_page', 15));
            
        return response()->json($clientes);
    }

    public function store(Request $request, $emisorId): JsonResponse
    {
        $data = $request->validate([
            'tipo_identificacion' => ['required', 'string', 'max:10'],
            'identificacion' => ['required', 'string', 'max:20'],
            'razon_social' => ['required', 'string', 'max:300'],
            'nombre_comercial' => ['nullable', 'string', 'max:300'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'email' => ['nullable', 'email'],
            'telefono' => ['nullable', 'string', 'max:50'],
        ]);

        $data['emisor_id'] = $emisorId;
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $cliente = Cliente::create($data);

        return response()->json($cliente, 201);
    }

    public function show($emisorId, $id): JsonResponse
    {
        $cliente = Cliente::where('emisor_id', $emisorId)->findOrFail($id);
        return response()->json($cliente);
    }

    public function update(Request $request, $emisorId, $id): JsonResponse
    {
        $cliente = Cliente::where('emisor_id', $emisorId)->findOrFail($id);

        $data = $request->validate([
            'tipo_identificacion' => ['required', 'string', 'max:10'],
            'identificacion' => ['required', 'string', 'max:20'],
            'razon_social' => ['required', 'string', 'max:300'],
            'nombre_comercial' => ['nullable', 'string', 'max:300'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'email' => ['nullable', 'email'],
            'telefono' => ['nullable', 'string', 'max:50'],
        ]);

        $data['updated_by'] = Auth::id();

        $cliente->update($data);

        return response()->json($cliente);
    }

    public function destroy($emisorId, $id): JsonResponse
    {
        $cliente = Cliente::where('emisor_id', $emisorId)->findOrFail($id);
        $cliente->delete();
        return response()->json(null, 204);
    }
}
