<?php

namespace App\Http\Controllers;

use App\Models\TipoRetencion;
use App\Http\Requests\StoreTipoRetencionRequest;
use App\Http\Requests\UpdateTipoRetencionRequest;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TipoRetencionController extends Controller
{
    /**
     * Listar tipos de retención con paginación y filtros.
     * HU2: Visualización de Tipos de Retención
     */
    public function index(Request $request)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();
            
            if (!$currentUser) {
                return response()->json([
                    'message' => 'No autenticado'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Solo admin puede acceder
            if ($currentUser->role !== UserRole::ADMINISTRADOR) {
                return response()->json([
                    'message' => 'No tienes permiso para acceder a esta funcionalidad'
                ], Response::HTTP_FORBIDDEN);
            }

            // Parámetros de paginación
            $page = max(1, (int)($request->input('page', 1)));
            $perPage = in_array((int)$request->input('per_page', 10), [5, 10, 15, 25, 50]) 
                ? (int)$request->input('per_page', 10) 
                : 10;
            
            // Parámetros de ordenamiento (por defecto: nombre ascendente)
            $sortBy = $request->input('sort_by', 'nombre');
            $sortDir = strtolower($request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

            // Validar columnas permitidas para ordenamiento
            $allowedSortColumns = [
                'tipo_retencion', 'nombre', 'codigo', 'porcentaje', 
                'created_at', 'updated_at'
            ];
            if (!in_array($sortBy, $allowedSortColumns)) {
                $sortBy = 'nombre';
            }

            // Construir query
            $query = TipoRetencion::query()
                ->with(['createdBy:id,username,nombres,apellidos', 'updatedBy:id,username,nombres,apellidos']);

            // === FILTROS POR RANGO DE FECHAS ===
            
            // Fecha de creación
            if ($request->filled('fecha_creacion_desde')) {
                $query->whereDate('created_at', '>=', $request->input('fecha_creacion_desde'));
            }
            if ($request->filled('fecha_creacion_hasta')) {
                $query->whereDate('created_at', '<=', $request->input('fecha_creacion_hasta'));
            }
            
            // Fecha de actualización
            if ($request->filled('fecha_actualizacion_desde')) {
                $query->whereDate('updated_at', '>=', $request->input('fecha_actualizacion_desde'));
            }
            if ($request->filled('fecha_actualizacion_hasta')) {
                $query->whereDate('updated_at', '<=', $request->input('fecha_actualizacion_hasta'));
            }

            // === FILTRO POR TIPO DE RETENCIÓN (múltiple) ===
            if ($request->filled('tipos_retencion')) {
                $tipos = is_array($request->input('tipos_retencion')) 
                    ? $request->input('tipos_retencion') 
                    : explode(',', $request->input('tipos_retencion'));
                $query->whereIn('tipo_retencion', $tipos);
            }

            // === FILTROS DE BÚSQUEDA ===
            
            // Nombre (parcial, case-insensitive)
            if ($request->filled('nombre')) {
                $query->where('nombre', 'LIKE', '%' . $request->input('nombre') . '%');
            }
            
            // Código (parcial, case-insensitive)
            if ($request->filled('codigo')) {
                $query->where('codigo', 'LIKE', '%' . $request->input('codigo') . '%');
            }
            
            // Porcentaje máximo
            if ($request->filled('porcentaje_max')) {
                $query->where('porcentaje', '<=', (float)$request->input('porcentaje_max'));
            }

            // Aplicar ordenamiento
            $query->orderBy($sortBy, $sortDir);

            // Ejecutar paginación
            $tiposRetencion = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'message' => 'Tipos de retención obtenidos exitosamente',
                'data' => $tiposRetencion->items(),
                'pagination' => [
                    'current_page' => $tiposRetencion->currentPage(),
                    'last_page' => $tiposRetencion->lastPage(),
                    'per_page' => $tiposRetencion->perPage(),
                    'total' => $tiposRetencion->total(),
                    'from' => $tiposRetencion->firstItem(),
                    'to' => $tiposRetencion->lastItem(),
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error al listar tipos de retención', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al obtener tipos de retención',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener un tipo de retención específico.
     */
    public function show($id)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();
            
            if (!$currentUser) {
                return response()->json([
                    'message' => 'No autenticado'
                ], Response::HTTP_UNAUTHORIZED);
            }

            if ($currentUser->role !== UserRole::ADMINISTRADOR) {
                return response()->json([
                    'message' => 'No tienes permiso para acceder a esta funcionalidad'
                ], Response::HTTP_FORBIDDEN);
            }

            $tipoRetencion = TipoRetencion::with(['createdBy:id,username,nombres,apellidos', 'updatedBy:id,username,nombres,apellidos'])
                ->find($id);

            if (!$tipoRetencion) {
                return response()->json([
                    'message' => 'Tipo de retención no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'message' => 'Tipo de retención obtenido exitosamente',
                'data' => $tipoRetencion
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error al obtener tipo de retención', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al obtener tipo de retención',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Registrar un nuevo tipo de retención.
     * HU1: Registro de Tipo de Retención
     */
    public function store(StoreTipoRetencionRequest $request)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();
            
            if (!$currentUser) {
                return response()->json([
                    'message' => 'No autenticado'
                ], Response::HTTP_UNAUTHORIZED);
            }

            if ($currentUser->role !== UserRole::ADMINISTRADOR) {
                return response()->json([
                    'message' => 'No tienes permiso para realizar esta acción'
                ], Response::HTTP_FORBIDDEN);
            }

            $validated = $request->validated();

            // Validar que el código solo contenga letras y números
            if (!TipoRetencion::isValidCodigo($validated['codigo'])) {
                return response()->json([
                    'message' => 'El código solo puede contener letras y números, sin espacios ni caracteres especiales',
                    'errors' => ['codigo' => ['El código solo puede contener letras y números']]
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Verificar duplicados
            $exists = TipoRetencion::where('tipo_retencion', $validated['tipo_retencion'])
                ->where('codigo', $validated['codigo'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Ya existe un tipo de retención con este código para el tipo seleccionado',
                    'errors' => ['codigo' => ['Este código ya existe para el tipo de retención seleccionado']]
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            DB::beginTransaction();

            $tipoRetencion = TipoRetencion::create([
                'tipo_retencion' => $validated['tipo_retencion'],
                'codigo' => strtoupper($validated['codigo']),
                'nombre' => $validated['nombre'],
                'porcentaje' => $validated['porcentaje'],
                'created_by_id' => $currentUser->id,
                'updated_by_id' => $currentUser->id,
            ]);

            DB::commit();

            Log::info('Tipo de retención creado', [
                'tipo_retencion_id' => $tipoRetencion->id,
                'created_by' => $currentUser->id,
            ]);

            // Cargar relaciones
            $tipoRetencion->load(['createdBy:id,username,nombres,apellidos', 'updatedBy:id,username,nombres,apellidos']);

            return response()->json([
                'message' => 'Tipo de retención registrado exitosamente',
                'data' => $tipoRetencion
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al crear tipo de retención', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al registrar tipo de retención',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Actualizar un tipo de retención.
     * HU3: Actualización de Tipo de Retención
     */
    public function update(UpdateTipoRetencionRequest $request, $id)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();
            
            if (!$currentUser) {
                return response()->json([
                    'message' => 'No autenticado'
                ], Response::HTTP_UNAUTHORIZED);
            }

            if ($currentUser->role !== UserRole::ADMINISTRADOR) {
                return response()->json([
                    'message' => 'No tienes permiso para realizar esta acción'
                ], Response::HTTP_FORBIDDEN);
            }

            $tipoRetencion = TipoRetencion::find($id);

            if (!$tipoRetencion) {
                return response()->json([
                    'message' => 'Tipo de retención no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validated();

            // Verificar contraseña del administrador
            if (!Hash::check($validated['password'], $currentUser->password)) {
                return response()->json([
                    'message' => 'La contraseña ingresada es incorrecta',
                    'errors' => ['password' => ['La contraseña es incorrecta']]
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Validar que el código solo contenga letras y números
            if (isset($validated['codigo']) && !TipoRetencion::isValidCodigo($validated['codigo'])) {
                return response()->json([
                    'message' => 'El código solo puede contener letras y números, sin espacios ni caracteres especiales',
                    'errors' => ['codigo' => ['El código solo puede contener letras y números']]
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Verificar duplicados (excluyendo el registro actual)
            if (isset($validated['tipo_retencion']) || isset($validated['codigo'])) {
                $tipoCheck = $validated['tipo_retencion'] ?? $tipoRetencion->tipo_retencion;
                $codigoCheck = $validated['codigo'] ?? $tipoRetencion->codigo;
                
                $exists = TipoRetencion::where('tipo_retencion', $tipoCheck)
                    ->where('codigo', $codigoCheck)
                    ->where('id', '!=', $id)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'message' => 'Ya existe un tipo de retención con este código para el tipo seleccionado',
                        'errors' => ['codigo' => ['Este código ya existe para el tipo de retención seleccionado']]
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            DB::beginTransaction();

            // Actualizar campos (excluir password)
            $updateData = collect($validated)->except('password')->toArray();
            
            if (isset($updateData['codigo'])) {
                $updateData['codigo'] = strtoupper($updateData['codigo']);
            }
            
            $updateData['updated_by_id'] = $currentUser->id;
            
            $tipoRetencion->update($updateData);

            DB::commit();

            Log::info('Tipo de retención actualizado', [
                'tipo_retencion_id' => $tipoRetencion->id,
                'updated_by' => $currentUser->id,
            ]);

            // Recargar relaciones
            $tipoRetencion->load(['createdBy:id,username,nombres,apellidos', 'updatedBy:id,username,nombres,apellidos']);

            return response()->json([
                'message' => 'Tipo de retención actualizado exitosamente',
                'data' => $tipoRetencion
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al actualizar tipo de retención', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al actualizar tipo de retención',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Eliminar un tipo de retención.
     * HU4: Eliminación de Tipo de Retención
     */
    public function destroy(Request $request, $id)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();
            
            if (!$currentUser) {
                return response()->json([
                    'message' => 'No autenticado'
                ], Response::HTTP_UNAUTHORIZED);
            }

            if ($currentUser->role !== UserRole::ADMINISTRADOR) {
                return response()->json([
                    'message' => 'No tienes permiso para realizar esta acción'
                ], Response::HTTP_FORBIDDEN);
            }

            $tipoRetencion = TipoRetencion::find($id);

            if (!$tipoRetencion) {
                return response()->json([
                    'message' => 'Tipo de retención no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }

            // Verificar contraseña
            $password = $request->input('password');
            if (!$password || !Hash::check($password, $currentUser->password)) {
                return response()->json([
                    'message' => 'La contraseña ingresada es incorrecta',
                    'errors' => ['password' => ['La contraseña es incorrecta']]
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            DB::beginTransaction();

            $tipoRetencionId = $tipoRetencion->id;
            $tipoRetencionNombre = $tipoRetencion->nombre;
            
            $tipoRetencion->delete();

            DB::commit();

            Log::info('Tipo de retención eliminado', [
                'tipo_retencion_id' => $tipoRetencionId,
                'nombre' => $tipoRetencionNombre,
                'deleted_by' => $currentUser->id,
            ]);

            return response()->json([
                'message' => 'Tipo de retención eliminado exitosamente'
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al eliminar tipo de retención', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al eliminar tipo de retención',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener opciones para el formulario.
     */
    public function getOpciones()
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();
            
            if (!$currentUser) {
                return response()->json([
                    'message' => 'No autenticado'
                ], Response::HTTP_UNAUTHORIZED);
            }

            if ($currentUser->role !== UserRole::ADMINISTRADOR) {
                return response()->json([
                    'message' => 'No tienes permiso para acceder a esta funcionalidad'
                ], Response::HTTP_FORBIDDEN);
            }

            return response()->json([
                'message' => 'Opciones obtenidas exitosamente',
                'data' => [
                    'tipos_retencion' => TipoRetencion::TIPOS_RETENCION,
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error al obtener opciones de tipos de retención', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al obtener opciones',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Verificar si existe un código para un tipo de retención.
     */
    public function checkCodigo(Request $request)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();
            
            if (!$currentUser) {
                return response()->json([
                    'message' => 'No autenticado'
                ], Response::HTTP_UNAUTHORIZED);
            }

            if ($currentUser->role !== UserRole::ADMINISTRADOR) {
                return response()->json([
                    'message' => 'No tienes permiso para acceder a esta funcionalidad'
                ], Response::HTTP_FORBIDDEN);
            }

            $tipoRetencion = $request->input('tipo_retencion');
            $codigo = $request->input('codigo');
            $excludeId = $request->input('exclude_id');

            if (!$tipoRetencion || !$codigo) {
                return response()->json([
                    'exists' => false
                ], Response::HTTP_OK);
            }

            $query = TipoRetencion::where('tipo_retencion', $tipoRetencion)
                ->where('codigo', strtoupper($codigo));

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            $exists = $query->exists();

            return response()->json([
                'exists' => $exists,
                'message' => $exists 
                    ? 'Ya existe un tipo de retención con este código para el tipo seleccionado'
                    : 'El código está disponible'
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error al verificar código de tipo de retención', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'exists' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
