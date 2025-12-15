<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UpdatePlanRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PlanController extends Controller
{
    /**
     * Listar todos los planes con paginación, búsqueda y filtros.
     * Solo accesible para administradores.
     */
    public function index(Request $request)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();
            
            // Verificar que sea administrador (el middleware ya lo verifica, pero añadimos seguridad adicional)
            if (!$currentUser || $currentUser->role->value !== 'administrador') {
                return response()->json([
                    'message' => 'No tienes permiso para acceder a esta funcionalidad',
                    'error' => 'Unauthorized'
                ], Response::HTTP_FORBIDDEN);
            }

            // Parámetros de paginación y filtrado
            $page = max(1, (int)($request->input('page', 1)));
            $perPage = max(5, min(100, (int)($request->input('per_page', 20))));
            $search = trim($request->input('search', ''));
            $estado = trim($request->input('estado', ''));
            $periodo = trim($request->input('periodo', ''));
            $sortBy = $request->input('sort_by', 'created_at');
            $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

            // Validar parámetros de ordenamiento
            $validSortColumns = ['id', 'nombre', 'precio', 'periodo', 'estado', 'created_at'];
            if (!in_array($sortBy, $validSortColumns)) {
                $sortBy = 'created_at';
            }

            // Construir query
            $query = Plan::query()->with(['createdBy:id,nombres,apellidos', 'updatedBy:id,nombres,apellidos']);

            // Aplicar búsqueda
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('observacion', 'like', "%{$search}%");
                });
            }

            // Filtrar por estado
            if (!empty($estado) && in_array($estado, ['Activo', 'Desactivado'])) {
                $query->where('estado', $estado);
            }

            // Filtrar por período
            if (!empty($periodo) && in_array($periodo, ['Mensual', 'Trimestral', 'Semestral', 'Anual', 'Bianual', 'Trianual'])) {
                $query->where('periodo', $periodo);
            }

            // Ordenar
            $query->orderBy($sortBy, $sortDir);

            // Paginar
            $planes = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'message' => 'Planes obtenidos exitosamente',
                'data' => $planes->items(),
                'pagination' => [
                    'current_page' => $planes->currentPage(),
                    'per_page' => $planes->perPage(),
                    'total' => $planes->total(),
                    'last_page' => $planes->lastPage(),
                    'from' => $planes->firstItem(),
                    'to' => $planes->lastItem(),
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error al listar planes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al obtener los planes',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Crear un nuevo plan.
     * Solo accesible para administradores.
     */
    public function store(StorePlanRequest $request)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();

            DB::beginTransaction();

            // Crear el plan
            $planData = $request->validated();
            $planData['created_by_id'] = $currentUser->id;

            $plan = Plan::create($planData);

            // Cargar relaciones
            $plan->load(['createdBy:id,nombres,apellidos']);

            DB::commit();

            Log::info('Plan creado exitosamente', [
                'plan_id' => $plan->id,
                'created_by' => $currentUser->id
            ]);

            return response()->json([
                'message' => '✅ Plan registrado exitosamente.',
                'data' => $plan
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al crear plan', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'message' => 'Error al crear el plan',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mostrar un plan específico.
     * Solo accesible para administradores.
     */
    public function show($id)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();

            // Verificar que sea administrador (el middleware ya lo verifica, pero añadimos seguridad adicional)
            if (!$currentUser || $currentUser->role->value !== 'administrador') {
                return response()->json([
                    'message' => 'No tienes permiso para acceder a esta funcionalidad',
                    'error' => 'Unauthorized'
                ], Response::HTTP_FORBIDDEN);
            }

            $plan = Plan::with(['createdBy:id,nombres,apellidos', 'updatedBy:id,nombres,apellidos'])
                        ->findOrFail($id);

            return response()->json([
                'message' => 'Plan obtenido exitosamente',
                'data' => $plan
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Plan no encontrado',
                'error' => 'Not Found'
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            Log::error('Error al obtener plan', [
                'plan_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al obtener el plan',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Actualizar un plan existente.
     * Solo accesible para administradores.
     */
    public function update(UpdatePlanRequest $request, $id)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();

            DB::beginTransaction();

            $plan = Plan::findOrFail($id);

            // Actualizar el plan
            $planData = $request->validated();
            $planData['updated_by_id'] = $currentUser->id;

            $plan->update($planData);

            // Recargar relaciones
            $plan->load(['createdBy:id,nombres,apellidos', 'updatedBy:id,nombres,apellidos']);

            DB::commit();

            Log::info('Plan actualizado exitosamente', [
                'plan_id' => $plan->id,
                'updated_by' => $currentUser->id
            ]);

            return response()->json([
                'message' => 'Plan actualizado exitosamente',
                'data' => $plan
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Plan no encontrado',
                'error' => 'Not Found'
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al actualizar plan', [
                'plan_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'message' => 'Error al actualizar el plan',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Eliminar un plan (soft delete).
     * Solo accesible para administradores.
     */
    public function destroy($id)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();

            // Verificar que sea administrador (el middleware ya lo verifica, pero añadimos seguridad adicional)
            if (!$currentUser || $currentUser->role->value !== 'administrador') {
                return response()->json([
                    'message' => 'No tienes permiso para acceder a esta funcionalidad',
                    'error' => 'Unauthorized'
                ], Response::HTTP_FORBIDDEN);
            }

            DB::beginTransaction();

            $plan = Plan::findOrFail($id);
            
            // Soft delete
            $plan->delete();

            DB::commit();

            Log::info('Plan eliminado exitosamente', [
                'plan_id' => $id,
                'deleted_by' => $currentUser->id
            ]);

            return response()->json([
                'message' => 'Plan eliminado exitosamente'
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Plan no encontrado',
                'error' => 'Not Found'
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al eliminar plan', [
                'plan_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al eliminar el plan',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener lista de períodos disponibles.
     */
    public function periodos()
    {
        return response()->json([
            'message' => 'Períodos obtenidos exitosamente',
            'data' => [
                'Mensual',
                'Trimestral',
                'Semestral',
                'Anual',
                'Bianual',
                'Trianual'
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Obtener lista de estados disponibles.
     */
    public function estados()
    {
        return response()->json([
            'message' => 'Estados obtenidos exitosamente',
            'data' => [
                'Activo',
                'Desactivado'
            ]
        ], Response::HTTP_OK);
    }
}
