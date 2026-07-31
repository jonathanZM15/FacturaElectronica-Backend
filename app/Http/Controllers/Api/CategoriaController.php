<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoriaController extends Controller
{
    public function index($emisorId)
    {
        $categorias = Categoria::where('emisor_id', $emisorId)
            ->withCount('productos')
            ->get();
            
        return response()->json(['data' => $categorias]);
    }

    public function store(Request $request, $emisorId)
    {
        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categorias')->where(function ($query) use ($emisorId) {
                    return $query->where('emisor_id', $emisorId);
                })
            ],
            'descripcion' => 'nullable|string',
            'estado' => 'boolean',
            'color' => 'nullable|string|max:20',
        ]);

        $categoria = Categoria::create([
            'emisor_id' => $emisorId,
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'estado' => $validated['estado'] ?? true,
            'color' => $validated['color'] ?? '#6366f1',
        ]);

        return response()->json(['data' => $categoria], 201);
    }

    public function update(Request $request, $emisorId, $id)
    {
        $categoria = Categoria::where('emisor_id', $emisorId)->findOrFail($id);

        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categorias')->where(function ($query) use ($emisorId) {
                    return $query->where('emisor_id', $emisorId);
                })->ignore($id)
            ],
            'descripcion' => 'nullable|string',
            'estado' => 'boolean',
            'color' => 'nullable|string|max:20',
        ]);

        $categoria->update($validated);

        return response()->json(['data' => $categoria]);
    }

    public function destroy($emisorId, $id)
    {
        $categoria = Categoria::where('emisor_id', $emisorId)->findOrFail($id);

        if ($categoria->productos()->count() > 0) {
            return response()->json(['message' => 'No se puede eliminar la categoría porque tiene productos asociados.'], 409);
        }

        $categoria->delete();

        return response()->json(null, 204);
    }
}
