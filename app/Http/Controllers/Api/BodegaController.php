<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\Establecimiento;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Enums\TipoBodega;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rule;

class BodegaController extends Controller
{
    use \App\Traits\ResolvesEmisor;

    public function index(Request $request, string $emisorId): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);
        if (!$resolvedId) {
            return response()->json(['data' => []]);
        }

        $query = Bodega::with(['creador:id,name', 'establecimiento:id,codigo,nombre,emisor_id'])
            ->whereHas('establecimiento', function ($q) use ($resolvedId) {
                $q->where('emisor_id', $resolvedId);
            });

        if ($request->filled('establecimiento_id')) {
            $query->where('establecimiento_id', $request->input('establecimiento_id'));
        }

        $bodegas = $query->get();
        return response()->json(['data' => $bodegas]);
    }

    public function store(Request $request, string $emisorId): JsonResponse
    {
        // 0. Resolver emisor válido
        $resolvedId = $this->resolveEmisorId($emisorId);
        if (!$resolvedId) {
            return response()->json(['message' => 'No existe un emisor válido registrado en el sistema.'], 404);
        }
        $emisorId = (string) $resolvedId;

        // 1. Resolver establecimiento_id
        $establecimientoId = $request->input('establecimiento_id');
        if ($establecimientoId) {
            $establecimiento = Establecimiento::where('emisor_id', $emisorId)->findOrFail($establecimientoId);
        } else {
            $establecimiento = Establecimiento::where('emisor_id', $emisorId)->first();
            if (!$establecimiento) {
                $establecimiento = Establecimiento::create([
                    'emisor_id' => $emisorId,
                    'codigo' => '001',
                    'estado' => 'ABIERTO',
                    'nombre' => 'Establecimiento Principal',
                    'direccion' => 'S/N',
                ]);
            }
            $establecimientoId = $establecimiento->id;
        }

        // 2. Validar
        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('bodegas')->where(function ($query) use ($establecimientoId, $request) {
                    return $query->where('establecimiento_id', $establecimientoId)
                        ->where('tipo', $request->input('tipo'))
                        ->whereNull('deleted_at');
                })
            ],
            'tipo' => ['required', new Enum(TipoBodega::class)],
            'permite_venta' => ['nullable', 'boolean'],
        ], [
            'nombre.unique' => 'Ya existe una bodega con este nombre y tipo en este establecimiento.'
        ]);

        // 3. Generar código si no viene
        $siguienteNum = Bodega::where('establecimiento_id', $establecimientoId)->count() + 1;
        $codigo = 'BOD-' . str_pad($siguienteNum, 2, '0', STR_PAD_LEFT);

        $bodega = Bodega::create([
            'establecimiento_id' => $establecimientoId,
            'codigo' => $codigo,
            'nombre' => $validated['nombre'],
            'tipo' => $validated['tipo'],
            'permite_venta' => $request->input('permite_venta', true),
            'creador_id' => auth()->id() ?? 1,
        ]);

        $bodega->load(['creador:id,name', 'establecimiento:id,codigo,nombre,emisor_id']);

        return response()->json(['message' => 'Bodega creada exitosamente', 'data' => $bodega], 201);
    }

    public function show(string $emisorId, string $id): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId) ?? (int)$emisorId;

        $bodega = Bodega::with(['creador:id,name', 'establecimiento:id,codigo,nombre,emisor_id'])
            ->whereHas('establecimiento', function ($q) use ($resolvedId) {
                $q->where('emisor_id', $resolvedId);
            })->findOrFail($id);

        return response()->json(['data' => $bodega]);
    }

    public function update(Request $request, string $emisorId, string $id): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId) ?? (int)$emisorId;

        $bodega = Bodega::whereHas('establecimiento', function ($q) use ($resolvedId) {
            $q->where('emisor_id', $resolvedId);
        })->findOrFail($id);

        $establecimientoId = $bodega->establecimiento_id;

        $validated = $request->validate([
            'nombre' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('bodegas')->where(function ($query) use ($establecimientoId, $request, $bodega) {
                    $tipo = $request->input('tipo') ?? ($bodega->tipo instanceof TipoBodega ? $bodega->tipo->value : (string) $bodega->tipo);
                    return $query->where('establecimiento_id', $establecimientoId)
                        ->where('tipo', $tipo)
                        ->whereNull('deleted_at');
                })->ignore($id)
            ],
            'tipo' => ['sometimes', 'required', new Enum(TipoBodega::class)],
            'permite_venta' => ['sometimes', 'boolean'],
        ], [
            'nombre.unique' => 'Ya existe una bodega con este nombre y tipo en este establecimiento.'
        ]);

        $bodega->update($validated);
        $bodega->load(['creador:id,name', 'establecimiento:id,codigo,nombre,emisor_id']);

        return response()->json(['message' => 'Bodega actualizada', 'data' => $bodega]);
    }

    public function destroy(string $emisorId, string $id): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId) ?? (int)$emisorId;

        $bodega = Bodega::whereHas('establecimiento', function ($q) use ($resolvedId) {
            $q->where('emisor_id', $resolvedId);
        })->findOrFail($id);

        $bodega->delete();

        return response()->json(['message' => 'Bodega eliminada exitosamente']);
    }
}
