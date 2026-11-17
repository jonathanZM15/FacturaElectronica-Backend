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
        $request->validate(['establecimiento_id' => ['nullable', 'integer', 'min:1']]);
        $resolvedId = $this->resolveEmisorId($emisorId);
        \Illuminate\Support\Facades\Log::info("BodegaController::index called with emisorId: {$emisorId} resolved: " . ($resolvedId ?? 'NULL'));
        if (!$resolvedId) {
            return response()->json(['data' => []]);
        }

        $estIds = Establecimiento::where('emisor_id', $resolvedId)->pluck('id');

        $query = Bodega::with(['creador:id,nombres,apellidos,username', 'establecimiento:id,codigo,nombre,emisor_id'])
            ->whereIn('establecimiento_id', $estIds);

        \App\Services\BusinessAccess::scopeWarehouses($query, $request->user());

        if ($request->filled('establecimiento_id')) {
            $query->where('establecimiento_id', $request->input('establecimiento_id'));
        }

        $bodegas = $query->get();
        return response()->json(['data' => $bodegas]);
    }

    public function store(Request $request, string $emisorId): JsonResponse
    {
        $user = auth()->user();
        if ($user && $user->role === \App\Enums\UserRole::CAJERO) {
            return response()->json(['message' => 'El rol cajero no tiene permisos para crear bodegas.'], 403);
        }

        // 0. Resolver emisor válido
        $resolvedId = $this->resolveEmisorId($emisorId);
        if (!$resolvedId) {
            return response()->json(['message' => 'No existe un emisor válido registrado en el sistema.'], 404);
        }
        $emisorId = (string) $resolvedId;

        // 1. Resolver establecimiento_id
        $request->validate(['establecimiento_id' => ['nullable', 'integer', 'min:1'], 'tipo' => ['required', new Enum(TipoBodega::class)]]);
        $establecimientoId = $request->input('establecimiento_id');
        if ($establecimientoId) {
            $establecimiento = Establecimiento::where('emisor_id', $emisorId)->findOrFail($establecimientoId);
        } else {
            $establecimiento = Establecimiento::where('emisor_id', $emisorId)->first();
            if (!$establecimiento) {
                throw \Illuminate\Validation\ValidationException::withMessages(['establecimiento_id' => 'Registra primero una sucursal con sus datos reales.']);
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

        $validated['permite_venta'] = $validated['permite_venta'] ?? in_array($validated['tipo'], ['VENTA', 'ALMACEN'], true);
        $this->validateConfiguration($validated['tipo'], (bool) $validated['permite_venta']);

        // 3. Generar código si no viene
        $bodega = \Illuminate\Support\Facades\DB::transaction(function () use ($establecimientoId, $validated, $request, $emisorId) {
            Company::whereKey($emisorId)->lockForUpdate()->firstOrFail();
            $siguienteNum = Bodega::withTrashed()->where('establecimiento_id', $establecimientoId)->count() + 1;
            do { $codigo = 'BOD-' . str_pad($siguienteNum++, 2, '0', STR_PAD_LEFT); }
            while (Bodega::withTrashed()->where('establecimiento_id', $establecimientoId)->where('codigo', $codigo)->exists());
            return Bodega::create([
            'establecimiento_id' => $establecimientoId,
            'codigo' => $codigo,
            'nombre' => $validated['nombre'],
            'tipo' => $validated['tipo'],
            'permite_venta' => $validated['permite_venta'],
            'creador_id' => auth()->id(),
            ]);
        });

        $bodega->load(['creador:id,nombres,apellidos,username', 'establecimiento:id,codigo,nombre,emisor_id']);

        return response()->json(['message' => 'Bodega creada exitosamente', 'data' => $bodega], 201);
    }

    public function show(string $emisorId, string $id): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);
        if (!$resolvedId) {
            return response()->json(['message' => 'Emisor no encontrado.'], 404);
        }

        $bodega = Bodega::with(['creador:id,nombres,apellidos,username', 'establecimiento:id,codigo,nombre,emisor_id'])
            ->whereHas('establecimiento', function ($q) use ($resolvedId) {
                $q->where('emisor_id', $resolvedId);
            })->findOrFail($id);

        return response()->json(['data' => $bodega]);
    }

    public function update(Request $request, string $emisorId, string $id): JsonResponse
    {
        $user = auth()->user();
        if ($user && $user->role === \App\Enums\UserRole::CAJERO) {
            return response()->json(['message' => 'El rol cajero no tiene permisos para modificar bodegas.'], 403);
        }

        $resolvedId = $this->resolveEmisorId($emisorId);
        if (!$resolvedId) {
            return response()->json(['message' => 'Emisor no encontrado.'], 404);
        }

        $bodega = Bodega::whereHas('establecimiento', function ($q) use ($resolvedId) {
            $q->where('emisor_id', $resolvedId);
        })->findOrFail($id);

        $establecimientoId = $bodega->establecimiento_id;
        $request->validate(['tipo' => ['sometimes', 'required', new Enum(TipoBodega::class)]]);

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

        \Illuminate\Support\Facades\DB::transaction(function () use ($bodega, $resolvedId, $validated) {
            Company::whereKey($resolvedId)->lockForUpdate()->firstOrFail();
            $bodega->refresh();
            $type = $validated['tipo'] ?? $bodega->tipo->value;
            $validated['permite_venta'] = $validated['permite_venta'] ?? ($type !== $bodega->tipo->value ? in_array($type, ['VENTA', 'ALMACEN'], true) : $bodega->permite_venta);
            $this->validateConfiguration($type, (bool) $validated['permite_venta']);
            if ($type !== $bodega->tipo->value) {
                $hasStock = \App\Models\ProductoBodegaStock::where('bodega_id', $bodega->id)->where(fn ($q) => $q->where('stock_fisico', '!=', 0)->orWhere('stock_reservado', '!=', 0))->exists();
                if ($hasStock || \App\Models\Kardex::where('bodega_id', $bodega->id)->exists() || $bodega->tipo === TipoBodega::TRANSITO) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['tipo' => ['No se puede cambiar el tipo de una bodega interna, con existencias o historial.']]);
                }
            }
            if (Bodega::where('establecimiento_id', $bodega->establecimiento_id)->where('tipo', $type)->where('nombre', $validated['nombre'] ?? $bodega->nombre)->whereKeyNot($bodega->id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['nombre' => ['Ya existe una bodega con este nombre y tipo en este establecimiento.']]);
            }
            $bodega->update($validated);
        });
        $bodega->load(['creador:id,nombres,apellidos,username', 'establecimiento:id,codigo,nombre,emisor_id']);

        return response()->json(['message' => 'Bodega actualizada', 'data' => $bodega]);
    }

    public function destroy(string $emisorId, string $id): JsonResponse
    {
        $user = auth()->user();
        if ($user && $user->role === \App\Enums\UserRole::CAJERO) {
            return response()->json(['message' => 'El rol cajero no tiene permisos para eliminar bodegas.'], 403);
        }

        $resolvedId = $this->resolveEmisorId($emisorId);
        if (!$resolvedId) {
            return response()->json(['message' => 'Emisor no encontrado.'], 404);
        }

        $bodega = Bodega::whereHas('establecimiento', function ($q) use ($resolvedId) {
            $q->where('emisor_id', $resolvedId);
        })->findOrFail($id);

        $hasStock = \App\Models\ProductoBodegaStock::where('bodega_id', $bodega->id)->where(function ($q) {
            $q->where('stock_fisico', '!=', 0)->orWhere('stock_reservado', '!=', 0);
        })->exists();
        if ($hasStock || \App\Models\Kardex::where('bodega_id', $bodega->id)->exists()) {
            return response()->json(['message' => 'No se puede eliminar una bodega con existencias o historial.'], 422);
        }
        $bodega->delete();

        return response()->json(['message' => 'Bodega eliminada exitosamente']);
    }

    private function validateConfiguration(string $type, bool $allowsSales): void
    {
        if ($type === TipoBodega::TRANSITO->value) {
            throw \Illuminate\Validation\ValidationException::withMessages(['tipo' => ['La bodega de tránsito se administra únicamente mediante procesos internos.']]);
        }
        if ($allowsSales && !in_array($type, ['VENTA', 'ALMACEN'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['permite_venta' => ['Solo bodegas de venta o almacén pueden permitir ventas.']]);
        }
    }
}
