<?php

namespace App\Http\Controllers;

use App\Models\PuntoEmision;
use App\Models\Establecimiento;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PuntoEmisionController extends Controller
{
    /**
     * Listar todos los puntos de emisión de un establecimiento
     */
    public function index(string $companyId, string $establecimientoId): JsonResponse
    {
        try {
            $puntos = PuntoEmision::where('company_id', $companyId)
                ->where('establecimiento_id', $establecimientoId)
                ->get();

            return response()->json(['data' => $puntos, 'success' => true]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener un punto de emisión específico
     */
    public function show(string $companyId, string $establecimientoId, string $puntoId): JsonResponse
    {
        try {
            $punto = PuntoEmision::where('company_id', $companyId)
                ->where('establecimiento_id', $establecimientoId)
                ->findOrFail($puntoId);

            return response()->json(['data' => $punto, 'success' => true]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Punto de emisión no encontrado'], 404);
        }
    }

    /**
     * Crear un nuevo punto de emisión
     */
    public function store(string $companyId, string $establecimientoId, Request $request): JsonResponse
    {
        try {
            // Validar que el establecimiento existe y pertenece a la compañía
            $establecimiento = Establecimiento::where('company_id', $companyId)
                ->findOrFail($establecimientoId);

            $validated = $request->validate([
                'codigo' => 'required|string|size:3|unique:puntos_emision,codigo',
                'estado' => 'required|in:ACTIVO,DESACTIVADO',
                'nombre' => 'required|string|max:255',
                'secuencial_factura' => 'required|integer|min:1',
                'secuencial_liquidacion_compra' => 'required|integer|min:1',
                'secuencial_nota_credito' => 'required|integer|min:1',
                'secuencial_nota_debito' => 'required|integer|min:1',
                'secuencial_guia_remision' => 'required|integer|min:1',
                'secuencial_retencion' => 'required|integer|min:1',
                'secuencial_proforma' => 'required|integer|min:1',
            ]);

            $punto = PuntoEmision::create([
                'company_id' => $companyId,
                'establecimiento_id' => $establecimientoId,
                ...$validated
            ]);

            return response()->json(['data' => $punto, 'success' => true, 'message' => 'Punto de emisión creado exitosamente'], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Actualizar un punto de emisión
     */
    public function update(string $companyId, string $establecimientoId, string $puntoId, Request $request): JsonResponse
    {
        try {
            $punto = PuntoEmision::where('company_id', $companyId)
                ->where('establecimiento_id', $establecimientoId)
                ->findOrFail($puntoId);

            $validated = $request->validate([
                'codigo' => 'sometimes|string|size:3|unique:puntos_emision,codigo,' . $puntoId,
                'estado' => 'sometimes|in:ACTIVO,DESACTIVADO',
                'nombre' => 'sometimes|string|max:255',
                'secuencial_factura' => 'sometimes|integer|min:1',
                'secuencial_liquidacion_compra' => 'sometimes|integer|min:1',
                'secuencial_nota_credito' => 'sometimes|integer|min:1',
                'secuencial_nota_debito' => 'sometimes|integer|min:1',
                'secuencial_guia_remision' => 'sometimes|integer|min:1',
                'secuencial_retencion' => 'sometimes|integer|min:1',
                'secuencial_proforma' => 'sometimes|integer|min:1',
            ]);

            $punto->update($validated);

            return response()->json(['data' => $punto, 'success' => true, 'message' => 'Punto de emisión actualizado exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Eliminar un punto de emisión
     */
    public function destroy(string $companyId, string $establecimientoId, string $puntoId, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'password' => 'required|string',
            ]);

            // Verificar que el usuario está autenticado
            if (!auth()->check()) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            // Validar la contraseña del usuario autenticado
            if (!password_verify($validated['password'], auth()->user()->password)) {
                return response()->json(['message' => 'Contraseña incorrecta'], 401);
            }

            $punto = PuntoEmision::where('company_id', $companyId)
                ->where('establecimiento_id', $establecimientoId)
                ->findOrFail($puntoId);

            $punto->delete();

            return response()->json(['success' => true, 'message' => 'Punto de emisión eliminado exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
