<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Enums\TipoBodega;
use Illuminate\Validation\Rules\Enum;

class BodegaController extends Controller
{
    public function index(string $emisorId): JsonResponse
    {
        $bodegas = Bodega::with('creador:id,name')->where('emisor_id', $emisorId)->get();
        return response()->json(['data' => $bodegas]);
    }

    public function store(Request $request, string $emisorId): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('bodegas')->where(function ($query) use ($emisorId, $request) {
                    return $query->where('emisor_id', $emisorId)
                        ->where('tipo', $request->input('tipo'));
                })
            ],
            'tipo' => ['required', new Enum(TipoBodega::class)],
        ], [
            'nombre.unique' => 'Ya existe una bodega con este nombre y tipo.'
        ]);

        $bodega = Bodega::create([
            'emisor_id' => $emisorId,
            'nombre' => $validated['nombre'],
            'tipo' => $validated['tipo'],
            'creador_id' => auth()->id() ?? 1, // Default to 1 if not auth for testing purposes
        ]);

        return response()->json(['message' => 'Bodega creada exitosamente', 'data' => $bodega], 201);
    }

    public function show(string $emisorId, string $id): JsonResponse
    {
        $bodega = Bodega::with('creador:id,name')->where('emisor_id', $emisorId)->findOrFail($id);
        return response()->json(['data' => $bodega]);
    }

    public function update(Request $request, string $emisorId, string $id): JsonResponse
    {
        $bodega = Bodega::where('emisor_id', $emisorId)->findOrFail($id);

        $validated = $request->validate([
            'nombre' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('bodegas')->where(function ($query) use ($emisorId, $request) {
                    $tipo = $request->input('tipo') ?? Bodega::find($request->route('id'))->tipo;
                    return $query->where('emisor_id', $emisorId)
                        ->where('tipo', $tipo);
                })->ignore($id)
            ],
            'tipo' => ['sometimes', 'required', new Enum(TipoBodega::class)],
        ], [
            'nombre.unique' => 'Ya existe una bodega con este nombre y tipo.'
        ]);

        $bodega->update($validated);

        return response()->json(['message' => 'Bodega actualizada', 'data' => $bodega]);
    }

    public function destroy(string $emisorId, string $id): JsonResponse
    {
        $bodega = Bodega::where('emisor_id', $emisorId)->findOrFail($id);
        $bodega->delete();

        return response()->json(['message' => 'Bodega eliminada exitosamente']);
    }
}
