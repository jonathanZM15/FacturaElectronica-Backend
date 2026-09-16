<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoriaController extends Controller
{
    use \App\Traits\ResolvesEmisor;

    public function index($emisorId)
    {
        $resolvedId = $this->resolveEmisorId($emisorId);
        $categorias = Categoria::where('emisor_id', $resolvedId)
            ->withCount('productos')
            ->get();
            
        return response()->json(['data' => $categorias]);
    }

    public function store(Request $request, $emisorId)
    {
        $resolvedId = $this->resolveEmisorId($emisorId);
        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categorias')->where(function ($query) use ($resolvedId) {
                    return $query->where('emisor_id', $resolvedId);
                })
            ],
            'descripcion' => 'nullable|string',
            'estado' => 'boolean',
            'color' => 'nullable|string|max:20',
        ]);

        $categoria = Categoria::create([
            'emisor_id' => $resolvedId,
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'estado' => $validated['estado'] ?? true,
            'color' => $validated['color'] ?? '#6366f1',
        ]);

        return response()->json(['data' => $categoria], 201);
    }

    public function update(Request $request, $emisorId, $id)
    {
        $resolvedId = $this->resolveEmisorId($emisorId);
        $categoria = Categoria::where('emisor_id', $resolvedId)->findOrFail($id);

        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categorias')->where(function ($query) use ($resolvedId) {
                    return $query->where('emisor_id', $resolvedId);
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
        $resolvedId = $this->resolveEmisorId($emisorId);
        $categoria = Categoria::where('emisor_id', $resolvedId)->findOrFail($id);

        if ($categoria->productos()->count() > 0) {
            return response()->json(['message' => 'No se puede eliminar la categoría porque tiene productos asociados.'], 409);
        }

        $categoria->delete();

        return response()->json(null, 204);
    }
}
