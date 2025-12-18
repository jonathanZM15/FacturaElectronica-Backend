<?php

namespace App\Http\Controllers;

use App\Models\TipoImpuesto;
use App\Http\Requests\StoreTipoImpuestoRequest;
use App\Http\Requests\UpdateTipoImpuestoRequest;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TipoImpuestoController extends Controller
{
    /**
     * Listar tipos de impuesto con paginación y filtros.
     * HU2: Visualización de Tipos de Impuesto
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
                'tipo_impuesto', 'nombre', 'codigo', 'valor_tarifa', 
                'tipo_tarifa', 'estado', 'created_at', 'updated_at'
            ];
            if (!in_array($sortBy, $allowedSortColumns)) {
                $sortBy = 'nombre';
            }

            // Construir query
            $query = TipoImpuesto::query()
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

            // === FILTRO POR TIPO DE IMPUESTO (múltiple) ===
            if ($request->filled('tipos_impuesto')) {
                $tipos = is_array($request->input('tipos_impuesto')) 
                    ? $request->input('tipos_impuesto') 
                    : explode(',', $request->input('tipos_impuesto'));
                $query->whereIn('tipo_impuesto', $tipos);
            }

            // === FILTROS DE BÚSQUEDA ===
            
            // Nombre (parcial, case-insensitive)
            if ($request->filled('nombre')) {
                $query->where('nombre', 'LIKE', '%' . $request->input('nombre') . '%');
            }
            
            // Código (parcial)
            if ($request->filled('codigo')) {
                $query->where('codigo', 'LIKE', '%' . $request->input('codigo') . '%');
            }
            
            // Valor tarifa (menor o igual)
            if ($request->filled('valor_tarifa_max')) {
                $query->where('valor_tarifa', '<=', (float)$request->input('valor_tarifa_max'));
            }
            
            // Tipo de tarifa
            if ($request->filled('tipo_tarifa')) {
                $query->where('tipo_tarifa', $request->input('tipo_tarifa'));
            }
            
            // Estado
            if ($request->filled('estado')) {
                $query->where('estado', $request->input('estado'));
            }

            // Aplicar ordenamiento
            $query->orderBy($sortBy, $sortDir);

            // Ejecutar paginación
            $tiposImpuesto = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'message' => 'Tipos de impuesto obtenidos exitosamente',
                'data' => $tiposImpuesto->items(),
                'pagination' => [
                    'current_page' => $tiposImpuesto->currentPage(),
                    'last_page' => $tiposImpuesto->lastPage(),
                    'per_page' => $tiposImpuesto->perPage(),
                    'total' => $tiposImpuesto->total(),
                    'from' => $tiposImpuesto->firstItem(),
                    'to' => $tiposImpuesto->lastItem(),
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error al listar tipos de impuesto', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al obtener tipos de impuesto',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener un tipo de impuesto específico.
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

            $tipoImpuesto = TipoImpuesto::with([
                'createdBy:id,username,nombres,apellidos',
                'updatedBy:id,username,nombres,apellidos'
            ])->findOrFail($id);

            // Agregar información adicional
            $tipoImpuesto->tiene_productos = $tipoImpuesto->tieneProductosAsociados();
            $tipoImpuesto->cantidad_productos = $tipoImpuesto->contarProductosAsociados();
            $tipoImpuesto->tarifas_permitidas = $tipoImpuesto->getTarifasPermitidas();
            $tipoImpuesto->puede_cambiar_tarifa = $tipoImpuesto->puedeCambiarTarifa();

            return response()->json([
                'message' => 'Tipo de impuesto obtenido',
                'data' => $tipoImpuesto,
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Tipo de impuesto no encontrado'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener tipo de impuesto',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Crear un nuevo tipo de impuesto.
     * HU1: Registro de tipo de impuesto
     */
    public function store(StoreTipoImpuestoRequest $request)
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
                    'message' => 'No tienes permiso para crear tipos de impuesto'
                ], Response::HTTP_FORBIDDEN);
            }

            // Verificar duplicados case-insensitive para nombre
            $nombreExistente = TipoImpuesto::whereRaw('LOWER(nombre) = ?', [strtolower($request->nombre)])->exists();
            if ($nombreExistente) {
                return response()->json([
                    'message' => 'Ya existe un tipo de impuesto con este nombre.',
                    'errors' => ['nombre' => ['Ya existe un tipo de impuesto con este nombre.']]
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $tipoImpuesto = TipoImpuesto::create([
                'tipo_impuesto' => $request->tipo_impuesto,
                'tipo_tarifa' => $request->tipo_tarifa,
                'codigo' => $request->codigo,
                'nombre' => $request->nombre,
                'valor_tarifa' => $request->valor_tarifa,
                'estado' => $request->estado ?? 'Activo',
                'created_by_id' => $currentUser->id,
                'updated_by_id' => $currentUser->id,
            ]);

            $tipoImpuesto->load(['createdBy:id,username,nombres,apellidos', 'updatedBy:id,username,nombres,apellidos']);

            Log::info('Tipo de impuesto creado', [
                'tipo_impuesto_id' => $tipoImpuesto->id,
                'nombre' => $tipoImpuesto->nombre,
                'creado_por' => $currentUser->id,
            ]);

            return response()->json([
                'message' => '✅ Tipo de impuesto registrado exitosamente.',
                'data' => $tipoImpuesto,
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Error al crear tipo de impuesto', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al registrar tipo de impuesto',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Actualizar un tipo de impuesto existente.
     * HU3: Actualización de Tipo de Impuesto
     */
    public function update(UpdateTipoImpuestoRequest $request, $id)
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
                    'message' => 'No tienes permiso para actualizar tipos de impuesto'
                ], Response::HTTP_FORBIDDEN);
            }

            // Verificar contraseña
            if (!Hash::check($request->password, $currentUser->password)) {
                return response()->json([
                    'message' => '❌ Autenticación fallida. Contraseña incorrecta.',
                    'errors' => ['password' => ['Contraseña incorrecta']]
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $tipoImpuesto = TipoImpuesto::findOrFail($id);
            $tieneProductos = $tipoImpuesto->tieneProductosAsociados();
            $estadoAnterior = $tipoImpuesto->estado;
            $nuevoEstado = $request->input('estado', $estadoAnterior);

            // Verificar duplicados case-insensitive para nombre (excluyendo el actual)
            if ($request->has('nombre')) {
                $nombreExistente = TipoImpuesto::whereRaw('LOWER(nombre) = ?', [strtolower($request->nombre)])
                    ->where('id', '!=', $id)
                    ->exists();
                if ($nombreExistente) {
                    return response()->json([
                        'message' => 'Ya existe un tipo de impuesto con este nombre.',
                        'errors' => ['nombre' => ['Ya existe un tipo de impuesto con este nombre.']]
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            DB::beginTransaction();

            try {
                // Si tiene productos asociados, solo se puede editar el estado
                if ($tieneProductos) {
                    $camposRestringidos = ['tipo_impuesto', 'tipo_tarifa', 'codigo', 'nombre', 'valor_tarifa'];
                    foreach ($camposRestringidos as $campo) {
                        if ($request->has($campo) && $request->input($campo) != $tipoImpuesto->$campo) {
                            return response()->json([
                                'message' => "El campo '{$campo}' no puede editarse porque el tipo de impuesto se encuentra asociado a uno o más productos.",
                                'errors' => [$campo => ['Este campo no puede editarse porque el tipo de impuesto se encuentra asociado a uno o más productos.']]
                            ], Response::HTTP_UNPROCESSABLE_ENTITY);
                        }
                    }

                    // Solo actualizar estado
                    if ($request->has('estado')) {
                        $tipoImpuesto->estado = $request->estado;
                    }
                } else {
                    // Actualizar todos los campos editables
                    $tipoImpuesto->fill($request->only([
                        'tipo_impuesto',
                        'tipo_tarifa',
                        'codigo',
                        'nombre',
                        'valor_tarifa',
                        'estado',
                    ]));
                }

                $tipoImpuesto->updated_by_id = $currentUser->id;

                // Si se desactiva y tiene productos, eliminar asociaciones
                if ($estadoAnterior === 'Activo' && $nuevoEstado === 'Desactivado' && $tieneProductos) {
                    // Eliminar asociación con productos
                    if (\Illuminate\Support\Facades\Schema::hasTable('productos')) {
                        $productosAfectados = DB::table('productos')
                            ->where('tipo_impuesto_id', $id)
                            ->update(['tipo_impuesto_id' => null]);
                        
                        Log::warning('Productos desasociados por desactivación de tipo de impuesto', [
                            'tipo_impuesto_id' => $id,
                            'productos_afectados' => $productosAfectados,
                        ]);
                    }
                }

                $tipoImpuesto->save();

                DB::commit();

                $tipoImpuesto->load(['createdBy:id,username,nombres,apellidos', 'updatedBy:id,username,nombres,apellidos']);

                Log::info('Tipo de impuesto actualizado', [
                    'tipo_impuesto_id' => $tipoImpuesto->id,
                    'actualizado_por' => $currentUser->id,
                ]);

                return response()->json([
                    'message' => '✅ Tipo de impuesto actualizado exitosamente.',
                    'data' => $tipoImpuesto,
                ], Response::HTTP_OK);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Tipo de impuesto no encontrado'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Error al actualizar tipo de impuesto', [
                'tipo_impuesto_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al actualizar tipo de impuesto',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Eliminar un tipo de impuesto.
     * HU4: Eliminación de Tipo de Impuesto
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
                    'message' => 'No tienes permiso para eliminar tipos de impuesto'
                ], Response::HTTP_FORBIDDEN);
            }

            // Verificar contraseña
            $request->validate(['password' => 'required|string']);
            
            if (!Hash::check($request->password, $currentUser->password)) {
                return response()->json([
                    'message' => '❌ Autenticación fallida. Contraseña incorrecta.',
                    'errors' => ['password' => ['Contraseña incorrecta']]
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $tipoImpuesto = TipoImpuesto::findOrFail($id);

            // Verificar si tiene productos asociados
            if ($tipoImpuesto->tieneProductosAsociados()) {
                $cantidadProductos = $tipoImpuesto->contarProductosAsociados();
                return response()->json([
                    'message' => "No se puede eliminar el tipo de impuesto porque tiene {$cantidadProductos} producto(s) asociado(s). Primero debe desasociar o eliminar los productos relacionados.",
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $nombreEliminado = $tipoImpuesto->nombre;
            $tipoImpuesto->delete();

            Log::info('Tipo de impuesto eliminado', [
                'tipo_impuesto_id' => $id,
                'nombre' => $nombreEliminado,
                'eliminado_por' => $currentUser->id,
            ]);

            return response()->json([
                'message' => '✅ Tipo de impuesto eliminado exitosamente.',
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Tipo de impuesto no encontrado'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Error al eliminar tipo de impuesto', [
                'tipo_impuesto_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al eliminar tipo de impuesto',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener tipos de impuesto activos (para selector en productos).
     */
    public function activos()
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();
            
            if (!$currentUser) {
                return response()->json([
                    'message' => 'No autenticado'
                ], Response::HTTP_UNAUTHORIZED);
            }

            $tiposActivos = TipoImpuesto::activos()
                ->orderBy('tipo_impuesto')
                ->orderBy('nombre')
                ->get(['id', 'tipo_impuesto', 'tipo_tarifa', 'codigo', 'nombre', 'valor_tarifa']);

            return response()->json([
                'message' => 'Tipos de impuesto activos obtenidos',
                'data' => $tiposActivos,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener tipos de impuesto activos',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener opciones para los selectores del formulario.
     */
    public function opciones()
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();
            
            if (!$currentUser) {
                return response()->json([
                    'message' => 'No autenticado'
                ], Response::HTTP_UNAUTHORIZED);
            }

            return response()->json([
                'message' => 'Opciones obtenidas',
                'data' => [
                    'tipos_impuesto' => TipoImpuesto::TIPOS_IMPUESTO,
                    'tipos_tarifa' => TipoImpuesto::TIPOS_TARIFA,
                    'estados' => TipoImpuesto::ESTADOS,
                    'tarifa_por_tipo' => TipoImpuesto::TARIFA_POR_TIPO,
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener opciones',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Verificar si un código ya existe.
     */
    public function checkCodigo(Request $request)
    {
        try {
            $codigo = $request->query('codigo');
            $excludeId = $request->query('exclude_id');

            $query = TipoImpuesto::where('codigo', $codigo);
            
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            $existe = $query->exists();

            return response()->json([
                'exists' => $existe,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al verificar código',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Verificar si un nombre ya existe.
     */
    public function checkNombre(Request $request)
    {
        try {
            $nombre = $request->query('nombre');
            $excludeId = $request->query('exclude_id');

            $query = TipoImpuesto::whereRaw('LOWER(nombre) = ?', [strtolower($nombre)]);
            
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            $existe = $query->exists();

            return response()->json([
                'exists' => $existe,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al verificar nombre',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
