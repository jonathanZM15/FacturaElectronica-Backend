<?php

namespace App\Http\Controllers;

use App\Models\PuntoEmision;
use App\Models\Establecimiento;
use App\Models\Company;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PuntoEmisionController extends Controller
{
    /**
     * Validar permisos para acceder a un punto de emisión
     */
    private function checkPermissions(string $companyId, array $options = [])
    {
        $currentUser = auth()->user();
        $company = Company::findOrFail($companyId);
        
        $isAdmin = $currentUser->role === UserRole::ADMINISTRADOR;
        $isCreator = $company->created_by === $currentUser->id;
        $isAssignedEmissor = ($currentUser->role === UserRole::EMISOR && $currentUser->emisor_id === (int)$companyId);
        $isAssignedGerente = ($currentUser->role === UserRole::GERENTE && $currentUser->emisor_id === (int)$companyId);
        $isAssignedCajero = ($currentUser->role === UserRole::CAJERO && $currentUser->emisor_id === (int)$companyId);
        $allowLimitedRoles = $options['allowLimitedRoles'] ?? false;
        
        $allowed = $isAdmin || $isCreator || $isAssignedEmissor;
        if ($allowLimitedRoles) {
            $allowed = $allowed || $isAssignedGerente || $isAssignedCajero;
        }

        if (!$allowed) {
            return response()->json([
                'message' => 'No tienes permisos para acceder a los puntos de emisión de este emisor'
            ], 403);
        }

        if ($allowLimitedRoles && ($isAssignedEmissor || $isAssignedGerente || $isAssignedCajero)) {
            $establecimientoId = $options['establecimientoId'] ?? null;
            $puntoId = $options['puntoId'] ?? null;
            
            // Obtener los IDs de establecimientos y puntos asignados
            $establecimientosIds = $this->normalizeIds($currentUser->establecimientos_ids);
            $puntosIds = $this->normalizeIds($currentUser->puntos_emision_ids);
            
            // Si no tiene establecimientos directos pero tiene puntos, inferir establecimientos desde puntos
            if (empty($establecimientosIds) && !empty($puntosIds)) {
                $establecimientosIds = PuntoEmision::whereIn('id', $puntosIds)
                    ->pluck('establecimiento_id')
                    ->unique()
                    ->toArray();
            }

            if ($establecimientoId) {
                if (!empty($establecimientosIds) && !$this->idInArray((int)$establecimientoId, $establecimientosIds)) {
                    return response()->json([
                        'message' => 'No tienes permisos para acceder a este establecimiento'
                    ], 403);
                }
            }

            if ($puntoId) {
                if (!empty($puntosIds) && !$this->idInArray((int)$puntoId, $puntosIds)) {
                    return response()->json([
                        'message' => 'No tienes permisos para acceder a este punto de emisión'
                    ], 403);
                }
            }

            // Si tiene puntos específicos asignados, marcar como limitado para filtrar
            // Si no tiene puntos asignados (array vacío), tiene acceso completo al establecimiento
            $hasLimitedAccess = !empty($puntosIds);

            return [
                'limited' => $hasLimitedAccess,
                'puntos_ids' => $puntosIds
            ];
        }

        return ['limited' => false];
    }

    /**
     * Listar todos los puntos de emisión de un establecimiento
     */
    public function index(string $companyId, string $establecimientoId): JsonResponse
    {
        try {
            // Validar permisos
            $permInfo = $this->checkPermissions($companyId, [
                'allowLimitedRoles' => true,
                'establecimientoId' => $establecimientoId
            ]);
            if ($permInfo instanceof JsonResponse) return $permInfo;
            
            $puntos = PuntoEmision::with('user')
                ->where('company_id', $companyId)
                ->where('establecimiento_id', $establecimientoId)
                ->get();

            if (($permInfo['limited'] ?? false) && !empty($permInfo['puntos_ids'])) {
                $assigned = $permInfo['puntos_ids'];
                $puntos = $puntos->filter(function ($punto) use ($assigned) {
                    return $this->idInArray($punto->id, $assigned);
                })->values();
            }

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
            // Validar permisos
            $permInfo = $this->checkPermissions($companyId, [
                'allowLimitedRoles' => true,
                'establecimientoId' => $establecimientoId,
                'puntoId' => $puntoId
            ]);
            if ($permInfo instanceof JsonResponse) return $permInfo;
            
            $punto = PuntoEmision::with('user')
                ->where('company_id', $companyId)
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
            // Validar permisos
            $permInfo = $this->checkPermissions($companyId);
            if ($permInfo instanceof JsonResponse) return $permInfo;
            
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
            // Validar permisos
            $permInfo = $this->checkPermissions($companyId);
            if ($permInfo instanceof JsonResponse) return $permInfo;
            
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
            // Validar permisos
            $permInfo = $this->checkPermissions($companyId);
            if ($permInfo instanceof JsonResponse) return $permInfo;
            
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

    /**
     * Listar todos los puntos de emisión de un emisor (sin agrupar por establecimiento)
     * HU 2: Needed by the frontend to populate punto selection in user form
     * 
     * Query params:
     * - for_assignment=true: Lista todos los puntos de establecimientos accesibles (para asignar a otros usuarios)
     * - for_assignment=false (default): Filtra por puntos asignados al usuario actual
     */
    public function listByEmisor(string $id, Request $request): JsonResponse
    {
        try {
            $permInfo = $this->checkPermissions($id, [
                'allowLimitedRoles' => true
            ]);
            if ($permInfo instanceof JsonResponse) return $permInfo;

            // Castear id a integer para la query
            $emiId = (int) $id;
            $currentUser = auth()->user();
            $forAssignment = $request->query('for_assignment') === 'true';
            
            // Obtener todos los puntos que pertenecen a establecimientos del emisor
            $query = PuntoEmision::where('company_id', $emiId)
                ->select('id', 'company_id', 'establecimiento_id', 'codigo', 'nombre', 'estado');

            // Si el usuario es limitado (emisor/gerente/cajero con asignaciones específicas)
            if (($permInfo['limited'] ?? false)) {
                if ($forAssignment) {
                    // Para asignación: mostrar todos los puntos de los establecimientos accesibles
                    $establecimientosIds = $this->normalizeIds($currentUser->establecimientos_ids);
                    $puntosIds = $this->normalizeIds($currentUser->puntos_emision_ids);
                    
                    // Si no tiene establecimientos directos, inferir desde puntos
                    if (empty($establecimientosIds) && !empty($puntosIds)) {
                        $establecimientosIds = PuntoEmision::whereIn('id', $puntosIds)
                            ->pluck('establecimiento_id')
                            ->unique()
                            ->toArray();
                    }
                    
                    // Filtrar solo por establecimientos accesibles, no por puntos específicos
                    if (!empty($establecimientosIds)) {
                        $query->whereIn('establecimiento_id', $establecimientosIds);
                    }
                } else {
                    // Para uso propio: filtrar por puntos específicamente asignados
                    $puntosIds = $permInfo['puntos_ids'] ?? [];
                    if (!empty($puntosIds)) {
                        $query->whereIn('id', $puntosIds);
                    }
                }
            }
            
            $puntos = $query->get();

            \Log::info('Puntos de emisión para emisor', [
                'emisor_id' => $emiId,
                'puntos_count' => $puntos->count(),
                'puntos' => $puntos->toArray()
            ]);

            return response()->json([
                'data' => $puntos,
                'success' => true
            ]);
        } catch (\Exception $e) {
            \Log::error('Error al listar puntos de emisión', [
                'emisor_id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Error al listar puntos de emisión',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function normalizeIds($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Si el resultado es un string (doble codificación), decodificar de nuevo
                if (is_string($decoded)) {
                    $decoded = json_decode($decoded, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        return $decoded;
                    }
                }
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        if (is_numeric($value)) {
            return [(int) $value];
        }

        return [];
    }

    private function idInArray($id, array $ids): bool
    {
        if (in_array($id, $ids, true)) {
            return true;
        }

        if (in_array((string) $id, $ids, true)) {
            return true;
        }

        $intIds = array_map('intval', $ids);
        return in_array((int) $id, $intIds, true);
    }
}
