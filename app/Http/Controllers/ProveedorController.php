<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProveedorController extends Controller
{
    public function index(Request $request, $emisorId): JsonResponse
    {
        $q = Proveedor::where('emisor_id', $emisorId);

        if ($request->has('search')) {
            $search = $request->get('search');
            $q->where(function ($query) use ($search) {
                $query->where('identificacion', 'like', "%{$search}%")
                      ->orWhere('razon_social', 'like', "%{$search}%");
            });
        }

        $proveedores = $q->orderBy('razon_social')
            ->paginate($request->get('per_page', 15));
            
        return response()->json($proveedores);
    }

    public function store(Request $request, $emisorId): JsonResponse
    {
        $data = $request->validate([
            'tipo_identificacion' => ['required', 'string', 'max:10'],
            'identificacion' => ['required', 'string', 'max:20'],
            'razon_social' => ['required', 'string', 'max:300'],
            'nombre_comercial' => ['nullable', 'string', 'max:300'],
            'direccion' => ['nullable', 'string', 'max:300'],
            'email' => ['nullable', 'email'],
            'telefono' => ['nullable', 'string', 'max:50'],
        ]);

        $data['emisor_id'] = $emisorId;
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $proveedor = Proveedor::create($data);

        return response()->json($proveedor, 201);
    }

    public function show($emisorId, $id): JsonResponse
    {
        $proveedor = Proveedor::where('emisor_id', $emisorId)->findOrFail($id);
        return response()->json($proveedor);
    }

    public function update(Request $request, $emisorId, $id): JsonResponse
    {
        $proveedor = Proveedor::where('emisor_id', $emisorId)->findOrFail($id);

        $data = $request->validate([
            'tipo_identificacion' => ['required', 'string', 'max:10'],
            'identificacion' => ['required', 'string', 'max:20'],
            'razon_social' => ['required', 'string', 'max:300'],
            'nombre_comercial' => ['nullable', 'string', 'max:300'],
            'direccion' => ['nullable', 'string', 'max:300'],
            'email' => ['nullable', 'email'],
            'telefono' => ['nullable', 'string', 'max:50'],
        ]);

        $data['updated_by'] = Auth::id();

        $proveedor->update($data);

        return response()->json($proveedor);
    }

    public function destroy($emisorId, $id): JsonResponse
    {
        $proveedor = Proveedor::where('emisor_id', $emisorId)->findOrFail($id);
        
        // Comprobar si tiene liquidaciones o facturas de compra asociadas (futuro)
        
        $proveedor->delete();

        return response()->json(null, 204);
    }
}
