<?php

namespace App\Http\Controllers;

use App\Models\Suscripcion;
use App\Models\SuscripcionComisionAudit;
use App\Models\SuscripcionEstadoAudit;
use App\Models\Plan;
use App\Models\Company;
use App\Models\User;
use App\Http\Requests\StoreSuscripcionRequest;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Services\SuscripcionEstadoService;
use Carbon\Carbon;

class SuscripcionController extends Controller
{
    /**
     * Listar suscripciones de un emisor con paginación y filtros.
     * Soporta filtros por rango de fechas, búsqueda y ordenamiento.
     */
    public function index(Request $request, $emisorId)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();
            
            if (!$currentUser) {
                return response()->json([
                    'message' => 'No autenticado'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Verificar permisos
            if (!in_array($currentUser->role, [UserRole::ADMINISTRADOR, UserRole::DISTRIBUIDOR])) {
                return response()->json([
                    'message' => 'No tienes permiso para ver suscripciones'
                ], Response::HTTP_FORBIDDEN);
            }

            // Verificar que el emisor existe
            $emisor = Company::findOrFail($emisorId);

            // Si es distribuidor, verificar que el emisor le pertenece
            if ($currentUser->role === UserRole::DISTRIBUIDOR) {
                if ($emisor->created_by_id !== $currentUser->id) {
                    return response()->json([
                        'message' => 'No tienes acceso a este emisor'
                    ], Response::HTTP_FORBIDDEN);
                }
            }

            // Parámetros de paginación y filtrado
            $page = max(1, (int)($request->input('page', 1)));
            $perPage = in_array((int)$request->input('per_page', 10), [5, 10, 15, 25, 50]) 
                ? (int)$request->input('per_page', 10) 
                : 10;
            
            // Parámetros de ordenamiento
            $sortBy = $request->input('sort_by', 'created_at');
            $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

            // Construir query con todas las relaciones necesarias
            $query = Suscripcion::query()
                ->where('emisor_id', $emisorId)
                ->with([
                    'plan:id,nombre,periodo,cantidad_comprobantes,precio,comprobantes_minimos,dias_minimos',
                    'createdBy:id,username,nombres,apellidos,role',
                    'updatedBy:id,username,nombres,apellidos'
                ]);

            // === FILTROS POR RANGO DE FECHAS ===
            
            // Fecha de registro (created_at)
            if ($request->filled('fecha_registro_desde')) {
                $query->whereDate('created_at', '>=', $request->input('fecha_registro_desde'));
            }
            if ($request->filled('fecha_registro_hasta')) {
                $query->whereDate('created_at', '<=', $request->input('fecha_registro_hasta'));
            }
            
            // Fecha de actualización (updated_at)
            if ($request->filled('fecha_actualizacion_desde')) {
                $query->whereDate('updated_at', '>=', $request->input('fecha_actualizacion_desde'));
            }
            if ($request->filled('fecha_actualizacion_hasta')) {
                $query->whereDate('updated_at', '<=', $request->input('fecha_actualizacion_hasta'));
            }
            
            // Fecha de inicio
            if ($request->filled('fecha_inicio_desde')) {
                $query->whereDate('fecha_inicio', '>=', $request->input('fecha_inicio_desde'));
            }
            if ($request->filled('fecha_inicio_hasta')) {
                $query->whereDate('fecha_inicio', '<=', $request->input('fecha_inicio_hasta'));
            }
            
            // Fecha de fin
            if ($request->filled('fecha_fin_desde')) {
                $query->whereDate('fecha_fin', '>=', $request->input('fecha_fin_desde'));
            }
            if ($request->filled('fecha_fin_hasta')) {
                $query->whereDate('fecha_fin', '<=', $request->input('fecha_fin_hasta'));
            }

            // === FILTROS DE BÚSQUEDA ===
            
            // Filtrar por plan (nombre)
            if ($request->filled('plan')) {
                $query->whereHas('plan', function ($q) use ($request) {
                    $q->where('nombre', 'ilike', '%' . $request->input('plan') . '%');
                });
            }
            
            // Filtrar por cantidad de comprobantes del plan (>= valor)
            if ($request->filled('cantidad_comprobantes_min')) {
                $query->where('cantidad_comprobantes', '>=', (int)$request->input('cantidad_comprobantes_min'));
            }
            
            // Filtrar por comprobantes creados/usados (>= valor)
            if ($request->filled('comprobantes_usados_min')) {
                $query->where('comprobantes_usados', '>=', (int)$request->input('comprobantes_usados_min'));
            }
            
            // Filtrar por comprobantes restantes (<= valor)
            if ($request->filled('comprobantes_restantes_max')) {
                $query->whereRaw('(cantidad_comprobantes - comprobantes_usados) <= ?', [(int)$request->input('comprobantes_restantes_max')]);
            }
            
            // Filtrar por estado de suscripción
            if ($request->filled('estado_suscripcion')) {
                $query->where('estado_suscripcion', $request->input('estado_suscripcion'));
            }
            
            // Filtrar por estado de transacción
            if ($request->filled('estado_transaccion')) {
                $query->where('estado_transaccion', $request->input('estado_transaccion'));
            }
            
            // Filtrar por monto (<= valor)
            if ($request->filled('monto_max')) {
                $query->where('monto', '<=', (float)$request->input('monto_max'));
            }
            
            // Filtrar por forma de pago
            if ($request->filled('forma_pago')) {
                $query->where('forma_pago', $request->input('forma_pago'));
            }
            
            // Filtrar por nombre del usuario registrador
            if ($request->filled('usuario_registrador')) {
                $query->whereHas('createdBy', function ($q) use ($request) {
                    $search = $request->input('usuario_registrador');
                    $q->where(function ($subQ) use ($search) {
                        $subQ->where('username', 'ilike', '%' . $search . '%')
                             ->orWhere('nombres', 'ilike', '%' . $search . '%')
                             ->orWhere('apellidos', 'ilike', '%' . $search . '%');
                    });
                });
            }
            
            // Filtrar por estado de comisión
            if ($request->filled('estado_comision')) {
                $query->where('estado_comision', $request->input('estado_comision'));
            }

            // === ORDENAMIENTO ===
            // Ordenar por Vigente primero si no hay ordenamiento específico diferente a created_at
            if ($sortBy === 'created_at' && $sortDir === 'desc') {
                // Ordenar: Vigente primero, luego por fecha de inicio descendente
                $query->orderByRaw("CASE WHEN estado_suscripcion = 'Vigente' THEN 0 ELSE 1 END")
                      ->orderBy('fecha_inicio', 'desc');
            } else {
                // Usar el ordenamiento especificado por el usuario
                $query->orderBy($sortBy, $sortDir);
            }

            // Paginar
            $suscripciones = $query->paginate($perPage, ['*'], 'page', $page);

            // Actualizar estados automáticos y formatear datos
            $items = collect($suscripciones->items())->map(function ($suscripcion) {
                $suscripcion->actualizarEstadoAutomatico();
                
                // Añadir campos calculados
                $suscripcion->comprobantes_restantes = $suscripcion->cantidad_comprobantes - $suscripcion->comprobantes_usados;
                
                return $suscripcion;
            });

            return response()->json([
                'message' => 'Suscripciones obtenidas exitosamente',
                'data' => $items,
                'pagination' => [
                    'current_page' => $suscripciones->currentPage(),
                    'per_page' => $suscripciones->perPage(),
                    'total' => $suscripciones->total(),
                    'last_page' => $suscripciones->lastPage(),
                    'from' => $suscripciones->firstItem(),
                    'to' => $suscripciones->lastItem(),
                ]
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Emisor no encontrado'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Error al listar suscripciones', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al obtener las suscripciones',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Crear una nueva suscripción.
     */
    public function store(StoreSuscripcionRequest $request, $emisorId)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();

            // Verificar que el emisor existe y coincide
            if ((int)$emisorId !== (int)$request->emisor_id) {
                return response()->json([
                    'message' => 'El emisor de la URL no coincide con el del formulario'
                ], Response::HTTP_BAD_REQUEST);
            }

            $emisor = Company::findOrFail($emisorId);
            
            // Si es distribuidor, verificar que el emisor le pertenece
            if ($currentUser->role === UserRole::DISTRIBUIDOR) {
                if ($emisor->created_by_id !== $currentUser->id) {
                    return response()->json([
                        'message' => 'No tienes acceso a este emisor'
                    ], Response::HTTP_FORBIDDEN);
                }
            }

            $plan = Plan::findOrFail($request->plan_id);

            DB::beginTransaction();

            // Preparar datos
            $data = $request->validated();
            $data['created_by_id'] = $currentUser->id;
            $data['ip_address'] = $request->ip();
            $data['user_agent'] = $request->userAgent();
            $data['comprobantes_usados'] = 0;

            // Determinar estado según el rol y la fecha
            $fechaInicio = Carbon::parse($data['fecha_inicio']);
            $hoy = Carbon::today();

            if ($currentUser->role === UserRole::DISTRIBUIDOR) {
                // Distribuidor siempre crea en Pendiente
                $data['estado_suscripcion'] = 'Pendiente';
                $data['estado_transaccion'] = 'Pendiente';
            } else {
                // Administrador
                if ($fechaInicio->greaterThan($hoy)) {
                    $data['estado_suscripcion'] = 'Programado';
                }
                // Si no se especifica, usar el valor del request o Vigente por defecto
                if (!isset($data['estado_suscripcion'])) {
                    $data['estado_suscripcion'] = $request->input('estado_suscripcion', 'Vigente');
                }
            }

            // Subir comprobante de pago si existe
            if ($request->hasFile('comprobante_pago')) {
                $comprobantePath = $request->file('comprobante_pago')
                    ->store('suscripciones/comprobantes', 'public');
                $data['comprobante_pago'] = $comprobantePath;
            }

            // Subir factura si existe
            if ($request->hasFile('factura')) {
                $facturaPath = $request->file('factura')
                    ->store('suscripciones/facturas', 'public');
                $data['factura'] = $facturaPath;
            }

            // Crear la suscripción
            $suscripcion = Suscripcion::create($data);

            // Cargar relaciones
            $suscripcion->load([
                'plan:id,nombre,periodo,cantidad_comprobantes,precio',
                'emisor:id,razon_social,nombre_comercial,ruc',
                'createdBy:id,nombres,apellidos,role'
            ]);

            DB::commit();

            // Enviar notificaciones por correo
            $this->enviarNotificacionesCreacion($suscripcion, $currentUser);

            Log::info('Suscripción creada exitosamente', [
                'suscripcion_id' => $suscripcion->id,
                'emisor_id' => $emisorId,
                'plan_id' => $plan->id,
                'created_by' => $currentUser->id,
                'ip_address' => $request->ip()
            ]);

            return response()->json([
                'message' => '✅ Suscripción creada exitosamente.',
                'data' => $suscripcion
            ], Response::HTTP_CREATED);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Recurso no encontrado',
                'error' => $e->getMessage()
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al crear suscripción', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->except(['comprobante_pago', 'factura'])
            ]);

            return response()->json([
                'message' => 'Error al registrar la suscripción.',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mostrar una suscripción específica.
     */
    public function show($emisorId, $id)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();

            $suscripcion = Suscripcion::where('emisor_id', $emisorId)
                ->where('id', $id)
                ->with([
                    'plan',
                    'emisor:id,razon_social,nombre_comercial,ruc',
                    'createdBy:id,nombres,apellidos,role,email',
                    'updatedBy:id,nombres,apellidos'
                ])
                ->firstOrFail();

            // Verificar permisos
            if ($currentUser->role === UserRole::DISTRIBUIDOR) {
                $emisor = Company::findOrFail($emisorId);
                if ($emisor->created_by_id !== $currentUser->id) {
                    return response()->json([
                        'message' => 'No tienes acceso a esta suscripción'
                    ], Response::HTTP_FORBIDDEN);
                }
            }

            // Actualizar estado automático
            $suscripcion->actualizarEstadoAutomatico();

            // Añadir información de auditoría solo para administradores
            $responseData = $suscripcion->toArray();
            if ($currentUser->role !== UserRole::ADMINISTRADOR) {
                unset($responseData['ip_address']);
                unset($responseData['user_agent']);
                unset($responseData['created_by']);
            }

            return response()->json([
                'message' => 'Suscripción obtenida exitosamente',
                'data' => $responseData
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Suscripción no encontrada'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Error al obtener suscripción', [
                'suscripcion_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error al obtener la suscripción',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Actualizar una suscripción existente.
     * Aplica reglas según rol del usuario y estado de la suscripción.
     */
    public function update(Request $request, $emisorId, $id)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();

            if (!$currentUser) {
                return response()->json([
                    'message' => 'No autenticado'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Verificar permisos básicos
            if (!in_array($currentUser->role, [UserRole::ADMINISTRADOR, UserRole::DISTRIBUIDOR])) {
                return response()->json([
                    'message' => 'No tienes permiso para editar suscripciones'
                ], Response::HTTP_FORBIDDEN);
            }

            // Obtener la suscripción
            $suscripcion = Suscripcion::where('emisor_id', $emisorId)
                ->where('id', $id)
                ->with(['plan', 'emisor'])
                ->firstOrFail();

            // Verificar que el emisor existe
            $emisor = Company::findOrFail($emisorId);

            // Si es distribuidor, verificar que el emisor le pertenece
            if ($currentUser->role === UserRole::DISTRIBUIDOR) {
                if ($emisor->created_by_id !== $currentUser->id) {
                    return response()->json([
                        'message' => 'No tienes acceso a este emisor'
                    ], Response::HTTP_FORBIDDEN);
                }
            }

            $isAdmin = $currentUser->role === UserRole::ADMINISTRADOR;
            $isDistribuidor = $currentUser->role === UserRole::DISTRIBUIDOR;
            $estado = $suscripcion->estado_suscripcion;
            $transaccionConfirmada = $suscripcion->estado_transaccion === 'Confirmada';
            $tieneComprobantesEmitidos = $suscripcion->comprobantes_usados > 0;

            // Definir campos editables según rol y estado
            $camposEditables = $this->getCamposEditables(
                $isAdmin,
                $isDistribuidor,
                $estado,
                $transaccionConfirmada,
                $tieneComprobantesEmitidos
            );

            // Validar datos de entrada
            $rules = $this->getValidationRules($camposEditables, $suscripcion);
            $validated = $request->validate($rules);

            DB::beginTransaction();

            // Guardar valores anteriores para auditoría de comisión
            $camposComision = ['estado_comision', 'monto_comision', 'comprobante_comision'];
            $valoresAnteriores = [];
            foreach ($camposComision as $campo) {
                $valoresAnteriores[$campo] = $suscripcion->$campo;
            }

            // Procesar campos permitidos
            $datosActualizados = [];
            $erroresCampos = [];

            foreach ($validated as $campo => $valor) {
                // Verificar si el campo es editable
                if (!in_array($campo, $camposEditables)) {
                    $erroresCampos[] = $campo;
                    continue;
                }

                // Manejar archivos
                if (in_array($campo, ['comprobante_pago', 'factura', 'comprobante_comision']) && $request->hasFile($campo)) {
                    $carpeta = $campo === 'comprobante_comision' ? 'suscripciones/comisiones' : 
                              ($campo === 'factura' ? 'suscripciones/facturas' : 'suscripciones/comprobantes');
                    $path = $request->file($campo)->store($carpeta, 'public');
                    $datosActualizados[$campo] = $path;
                } elseif (!in_array($campo, ['comprobante_pago', 'factura', 'comprobante_comision'])) {
                    // Validaciones especiales
                    if ($campo === 'cantidad_comprobantes') {
                        // Solo se puede aumentar, nunca disminuir
                        if ($valor < $suscripcion->cantidad_comprobantes) {
                            return response()->json([
                                'message' => 'La cantidad de comprobantes solo puede aumentarse, no disminuirse.',
                                'errors' => ['cantidad_comprobantes' => ['La cantidad debe ser mayor o igual a ' . $suscripcion->cantidad_comprobantes]]
                            ], Response::HTTP_UNPROCESSABLE_ENTITY);
                        }
                    }

                    if ($campo === 'plan_id' && $valor != $suscripcion->plan_id) {
                        // Si cambia el plan, recalcular fecha_fin
                        $nuevoPlan = Plan::findOrFail($valor);
                        $fechaInicio = isset($validated['fecha_inicio']) 
                            ? Carbon::parse($validated['fecha_inicio']) 
                            : Carbon::parse($suscripcion->fecha_inicio);
                        $datosActualizados['fecha_fin'] = Suscripcion::calcularFechaFin($fechaInicio, $nuevoPlan->periodo)->format('Y-m-d');
                    }

                    $datosActualizados[$campo] = $valor;
                }
            }

            // Si hay errores de campos no editables
            if (!empty($erroresCampos)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'No tiene permisos para editar este campo o la suscripción se encuentra bloqueada por estado.',
                    'campos_bloqueados' => $erroresCampos
                ], Response::HTTP_FORBIDDEN);
            }

            // Agregar campos de auditoría
            $datosActualizados['updated_by_id'] = $currentUser->id;

            // Actualizar la suscripción
            $suscripcion->update($datosActualizados);

            // Registrar auditoría de campos de comisión
            foreach ($camposComision as $campo) {
                if (isset($datosActualizados[$campo]) && $valoresAnteriores[$campo] !== $datosActualizados[$campo]) {
                    SuscripcionComisionAudit::registrarCambio(
                        $suscripcion->id,
                        $currentUser->id,
                        $currentUser->role->value,
                        $campo,
                        $valoresAnteriores[$campo],
                        $datosActualizados[$campo],
                        $request->ip(),
                        $request->userAgent()
                    );
                }
            }

            // Actualizar estado automático si es necesario
            $suscripcion->actualizarEstadoAutomatico();

            // Recargar relaciones
            $suscripcion->load([
                'plan:id,nombre,periodo,cantidad_comprobantes,precio',
                'emisor:id,razon_social,nombre_comercial,ruc',
                'createdBy:id,username,nombres,apellidos,role',
                'updatedBy:id,username,nombres,apellidos'
            ]);

            DB::commit();

            Log::info('Suscripción actualizada exitosamente', [
                'suscripcion_id' => $suscripcion->id,
                'emisor_id' => $emisorId,
                'updated_by' => $currentUser->id,
                'campos_actualizados' => array_keys($datosActualizados),
                'ip_address' => $request->ip()
            ]);

            return response()->json([
                'message' => '✅ Suscripción actualizada exitosamente.',
                'data' => $suscripcion
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Suscripción o recurso no encontrado'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar suscripción', [
                'suscripcion_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al actualizar la suscripción',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener los campos editables según rol, estado y condiciones.
     */
    private function getCamposEditables(
        bool $isAdmin,
        bool $isDistribuidor,
        string $estado,
        bool $transaccionConfirmada,
        bool $tieneComprobantesEmitidos
    ): array {
        $camposEditables = [];

        // Estados que permiten más edición
        $estadosFlexibles = ['Vigente', 'Suspendido', 'Pendiente', 'Programado'];
        $estadosRestringidos = ['Caducado', 'Sin comprobantes', 'Proximo a caducar', 'Pocos comprobantes', 'Proximo a caducar y con pocos comprobantes'];

        if ($isAdmin) {
            // === ADMINISTRADOR ===
            if (in_array($estado, $estadosFlexibles)) {
                // Campos base editables
                $camposEditables = [
                    'monto',
                    'cantidad_comprobantes',
                    'estado_suscripcion',
                    'forma_pago',
                    'comprobante_pago',
                    'factura',
                    'estado_transaccion',
                    'estado_comision',
                    'monto_comision',
                    'comprobante_comision',
                ];

                // Plan, fecha_inicio, fecha_fin solo si no hay comprobantes emitidos
                if (!$tieneComprobantesEmitidos) {
                    $camposEditables = array_merge($camposEditables, [
                        'plan_id',
                        'fecha_inicio',
                        'fecha_fin',
                    ]);
                }
            } elseif (in_array($estado, $estadosRestringidos)) {
                // Estados restringidos: solo algunos campos
                $camposEditables = [
                    'monto',
                    'cantidad_comprobantes',
                    'forma_pago',
                    'comprobante_pago',
                    'factura',
                    'estado_transaccion',
                    'estado_comision',
                    'monto_comision',
                    'comprobante_comision',
                ];
            }
        } elseif ($isDistribuidor) {
            // === DISTRIBUIDOR ===
            
            // Si la transacción está confirmada, solo puede editar comprobante y factura
            if ($transaccionConfirmada) {
                $camposEditables = [
                    'comprobante_pago',
                    'factura',
                ];
            } elseif (in_array($estado, ['Pendiente', 'Programado'])) {
                // Pendiente o Programado: puede editar más campos
                $camposEditables = [
                    'plan_id',
                    'fecha_inicio',
                    'fecha_fin',
                    'monto',
                    'cantidad_comprobantes',
                    'forma_pago',
                    'comprobante_pago',
                    'factura',
                ];
            } else {
                // Otros estados: solo comprobante y factura
                $camposEditables = [
                    'comprobante_pago',
                    'factura',
                ];
            }
        }

        return $camposEditables;
    }

    /**
     * Obtener reglas de validación dinámicas según campos editables.
     */
    private function getValidationRules(array $camposEditables, Suscripcion $suscripcion): array
    {
        $rules = [];

        foreach ($camposEditables as $campo) {
            switch ($campo) {
                case 'plan_id':
                    $rules['plan_id'] = 'sometimes|exists:planes,id';
                    break;
                case 'fecha_inicio':
                    $rules['fecha_inicio'] = 'sometimes|date';
                    break;
                case 'fecha_fin':
                    $rules['fecha_fin'] = 'sometimes|date|after_or_equal:fecha_inicio';
                    break;
                case 'monto':
                    $rules['monto'] = 'sometimes|numeric|min:0.01';
                    break;
                case 'cantidad_comprobantes':
                    $rules['cantidad_comprobantes'] = 'sometimes|integer|min:' . $suscripcion->cantidad_comprobantes;
                    break;
                case 'estado_suscripcion':
                    $rules['estado_suscripcion'] = 'sometimes|in:Vigente,Suspendido';
                    break;
                case 'forma_pago':
                    $rules['forma_pago'] = 'sometimes|in:Efectivo,Transferencia,Otro';
                    break;
                case 'comprobante_pago':
                    $rules['comprobante_pago'] = 'sometimes|file|mimes:jpg,jpeg,png|max:5120';
                    break;
                case 'factura':
                    $rules['factura'] = 'sometimes|file|mimes:pdf|max:10240';
                    break;
                case 'estado_transaccion':
                    $rules['estado_transaccion'] = 'sometimes|in:Pendiente,Confirmada';
                    break;
                case 'estado_comision':
                    $rules['estado_comision'] = 'sometimes|in:Sin comision,Pendiente,Pagada';
                    break;
                case 'monto_comision':
                    $rules['monto_comision'] = 'sometimes|numeric|min:0';
                    break;
                case 'comprobante_comision':
                    $rules['comprobante_comision'] = 'sometimes|file|mimes:jpg,jpeg,png,pdf|max:5120';
                    break;
            }
        }

        return $rules;
    }

    /**
     * Obtener los campos editables para una suscripción (endpoint para frontend).
     */
    public function camposEditables($emisorId, $id)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();

            if (!$currentUser) {
                return response()->json([
                    'message' => 'No autenticado'
                ], Response::HTTP_UNAUTHORIZED);
            }

            $suscripcion = Suscripcion::where('emisor_id', $emisorId)
                ->where('id', $id)
                ->firstOrFail();

            $isAdmin = $currentUser->role === UserRole::ADMINISTRADOR;
            $isDistribuidor = $currentUser->role === UserRole::DISTRIBUIDOR;
            $estado = $suscripcion->estado_suscripcion;
            $transaccionConfirmada = $suscripcion->estado_transaccion === 'Confirmada';
            $tieneComprobantesEmitidos = $suscripcion->comprobantes_usados > 0;

            $camposEditables = $this->getCamposEditables(
                $isAdmin,
                $isDistribuidor,
                $estado,
                $transaccionConfirmada,
                $tieneComprobantesEmitidos
            );

            // Campos que solo son visibles (lectura)
            $camposSoloLectura = [
                'created_by_id',
                'comprobantes_usados',
                'comprobantes_restantes',
            ];

            // Campos visibles para el distribuidor pero no editables
            if ($isDistribuidor) {
                $camposSoloLectura = array_merge($camposSoloLectura, [
                    'estado_suscripcion',
                    'estado_transaccion',
                    'estado_comision',
                    'monto_comision',
                    'comprobante_comision',
                ]);
            }

            return response()->json([
                'message' => 'Campos editables obtenidos',
                'data' => [
                    'campos_editables' => $camposEditables,
                    'campos_solo_lectura' => $camposSoloLectura,
                    'estado_suscripcion' => $estado,
                    'transaccion_confirmada' => $transaccionConfirmada,
                    'tiene_comprobantes_emitidos' => $tieneComprobantesEmitidos,
                    'is_admin' => $isAdmin,
                    'is_distribuidor' => $isDistribuidor,
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener campos editables',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener planes activos para el selector.
     */
    public function planesActivos()
    {
        try {
            $planes = Plan::where('estado', 'Activo')
                ->select('id', 'nombre', 'periodo', 'cantidad_comprobantes', 'precio', 'comprobantes_minimos', 'dias_minimos')
                ->orderBy('nombre')
                ->get()
                ->map(function ($plan) {
                    return [
                        'id' => $plan->id,
                        'nombre' => $plan->nombre,
                        'periodo' => $plan->periodo,
                        'cantidad_comprobantes' => $plan->cantidad_comprobantes,
                        'precio' => $plan->precio,
                        'comprobantes_minimos' => $plan->comprobantes_minimos,
                        'dias_minimos' => $plan->dias_minimos,
                        'label' => strtoupper($plan->nombre) . ' - ' . strtoupper($plan->periodo) . ' - ' . $plan->cantidad_comprobantes . ' C - $' . number_format($plan->precio, 2),
                    ];
                });

            return response()->json([
                'message' => 'Planes activos obtenidos exitosamente',
                'data' => $planes
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error al obtener planes activos', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error al obtener los planes',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Calcular fecha de fin según el plan y fecha de inicio.
     */
    public function calcularFechaFin(Request $request)
    {
        try {
            $request->validate([
                'plan_id' => 'required|exists:planes,id',
                'fecha_inicio' => 'required|date',
            ]);

            $plan = Plan::findOrFail($request->plan_id);
            $fechaInicio = Carbon::parse($request->fecha_inicio);
            $fechaFin = Suscripcion::calcularFechaFin($fechaInicio, $plan->periodo);

            return response()->json([
                'message' => 'Fecha calculada exitosamente',
                'data' => [
                    'fecha_fin' => $fechaFin->format('Y-m-d'),
                    'periodo' => $plan->periodo,
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al calcular la fecha',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Obtener estados disponibles.
     */
    public function estados()
    {
        return response()->json([
            'message' => 'Estados obtenidos exitosamente',
            'data' => [
                'manuales' => Suscripcion::ESTADOS_MANUALES,
                'todos' => [
                    'Vigente',
                    'Suspendido',
                    'Pendiente',
                    'Programado',
                    'Proximo a caducar',
                    'Pocos comprobantes',
                    'Proximo a caducar y con pocos comprobantes',
                    'Caducado',
                    'Sin comprobantes'
                ],
                'formas_pago' => ['Efectivo', 'Transferencia', 'Otro'],
                'estados_transaccion' => ['Pendiente', 'Confirmada'],
                'estados_comision' => ['Sin comision', 'Pendiente', 'Pagada'],
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Enviar notificaciones al crear suscripción.
     */
    private function enviarNotificacionesCreacion(Suscripcion $suscripcion, User $creador): void
    {
        try {
            // Si la suscripción está vigente, notificar a los usuarios del emisor con rol Emisor
            if ($suscripcion->estado_suscripcion === 'Vigente') {
                $usuariosEmisor = User::where('company_id', $suscripcion->emisor_id)
                    ->where('role', UserRole::EMISOR)
                    ->where('estado', 'activo')
                    ->get();

                foreach ($usuariosEmisor as $usuario) {
                    // TODO: Implementar el envío de correo con la plantilla correspondiente
                    Log::info('Notificación de suscripción vigente', [
                        'usuario_id' => $usuario->id,
                        'email' => $usuario->email,
                        'suscripcion_id' => $suscripcion->id
                    ]);
                }
            }

            // Si fue creada por un distribuidor, notificar a los administradores
            if ($creador->role === UserRole::DISTRIBUIDOR) {
                $administradores = User::where('role', UserRole::ADMINISTRADOR)
                    ->where('estado', 'activo')
                    ->get();

                foreach ($administradores as $admin) {
                    // TODO: Implementar el envío de correo con la plantilla correspondiente
                    Log::info('Notificación a admin de nueva suscripción por distribuidor', [
                        'admin_id' => $admin->id,
                        'admin_email' => $admin->email,
                        'distribuidor' => $creador->nombres . ' ' . $creador->apellidos,
                        'suscripcion_id' => $suscripcion->id
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error al enviar notificaciones de suscripción', [
                'error' => $e->getMessage(),
                'suscripcion_id' => $suscripcion->id
            ]);
        }
    }

    /**
     * Cambiar estado de una suscripción manualmente.
     */
    public function cambiarEstado(Request $request, $emisorId, $id)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();

            if (!$currentUser) {
                return response()->json([
                    'message' => 'No autenticado'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Verificar permisos básicos
            if (!in_array($currentUser->role, [UserRole::ADMINISTRADOR, UserRole::DISTRIBUIDOR])) {
                return response()->json([
                    'message' => 'No tienes permiso para cambiar estados de suscripciones'
                ], Response::HTTP_FORBIDDEN);
            }

            // Validar entrada
            $validated = $request->validate([
                'nuevo_estado' => 'required|string|in:Vigente,Pendiente,Programado,Suspendido,Proximo a caducar,Pocos comprobantes,Proximo a caducar y con pocos comprobantes,Caducado,Sin comprobantes',
                'motivo' => 'nullable|string|max:255',
            ]);

            // Obtener la suscripción
            $suscripcion = Suscripcion::where('emisor_id', $emisorId)
                ->where('id', $id)
                ->with('plan')
                ->firstOrFail();

            // Verificar acceso al emisor
            $emisor = Company::findOrFail($emisorId);
            if ($currentUser->role === UserRole::DISTRIBUIDOR) {
                if ($emisor->created_by_id !== $currentUser->id) {
                    return response()->json([
                        'message' => 'No tienes acceso a este emisor'
                    ], Response::HTTP_FORBIDDEN);
                }
            }

            // Usar el servicio de estados
            $estadoService = new SuscripcionEstadoService();
            $resultado = $estadoService->ejecutarTransicionManual(
                $suscripcion,
                $validated['nuevo_estado'],
                $currentUser,
                $validated['motivo'] ?? null,
                $request->ip(),
                $request->userAgent()
            );

            if (!$resultado['valido']) {
                return response()->json([
                    'message' => "⚠️ {$resultado['mensaje']}"
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Recargar suscripción con relaciones
            $suscripcion->load([
                'plan:id,nombre,periodo,cantidad_comprobantes,precio',
                'emisor:id,razon_social,nombre_comercial,ruc',
            ]);

            Log::info('Estado de suscripción cambiado manualmente', [
                'suscripcion_id' => $suscripcion->id,
                'nuevo_estado' => $validated['nuevo_estado'],
                'user_id' => $currentUser->id,
                'ip_address' => $request->ip()
            ]);

            return response()->json([
                'message' => "✅ {$resultado['mensaje']}",
                'data' => $suscripcion
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Suscripción no encontrada'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado de suscripción', [
                'suscripcion_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al cambiar el estado de la suscripción',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener las transiciones de estado disponibles para una suscripción.
     */
    public function transicionesDisponibles($emisorId, $id)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();

            if (!$currentUser) {
                return response()->json([
                    'message' => 'No autenticado'
                ], Response::HTTP_UNAUTHORIZED);
            }

            $suscripcion = Suscripcion::where('emisor_id', $emisorId)
                ->where('id', $id)
                ->firstOrFail();

            // Verificar acceso
            $emisor = Company::findOrFail($emisorId);
            if ($currentUser->role === UserRole::DISTRIBUIDOR) {
                if ($emisor->created_by_id !== $currentUser->id) {
                    return response()->json([
                        'message' => 'No tienes acceso a este emisor'
                    ], Response::HTTP_FORBIDDEN);
                }
            }

            $estadoService = new SuscripcionEstadoService();
            $transiciones = $estadoService->getTransicionesDisponibles(
                $suscripcion->estado_suscripcion,
                $currentUser->role->value
            );

            return response()->json([
                'message' => 'Transiciones disponibles obtenidas',
                'data' => [
                    'estado_actual' => $suscripcion->estado_suscripcion,
                    'transiciones_disponibles' => $transiciones,
                    'is_admin' => $currentUser->role === UserRole::ADMINISTRADOR,
                ]
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Suscripción no encontrada'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener transiciones disponibles',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Eliminar una suscripción.
     * Solo se permite si:
     * - Estado de transacción es "Pendiente"
     * - Estado de suscripción es "Pendiente" o "Programado"
     * - No existen comprobantes emitidos asociados
     * 
     * HU8: Eliminación de Suscripción
     */
    public function destroy(Request $request, $emisorId, $id)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();

            if (!$currentUser) {
                return response()->json([
                    'message' => 'No autenticado'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Verificar permisos (Admin o Distribuidor)
            if (!in_array($currentUser->role, [UserRole::ADMINISTRADOR, UserRole::DISTRIBUIDOR])) {
                return response()->json([
                    'message' => 'No tienes permiso para eliminar suscripciones'
                ], Response::HTTP_FORBIDDEN);
            }

            // Verificar que el emisor existe
            $emisor = Company::findOrFail($emisorId);

            // Si es distribuidor, verificar que el emisor le pertenece
            if ($currentUser->role === UserRole::DISTRIBUIDOR) {
                if ($emisor->created_by_id !== $currentUser->id) {
                    return response()->json([
                        'message' => 'No tienes acceso a este emisor'
                    ], Response::HTTP_FORBIDDEN);
                }
            }

            // Obtener la suscripción
            $suscripcion = Suscripcion::where('emisor_id', $emisorId)
                ->where('id', $id)
                ->firstOrFail();

            // === VALIDACIONES PARA PERMITIR ELIMINACIÓN ===
            
            // 1. Verificar que el estado de transacción sea "Pendiente"
            if ($suscripcion->estado_transaccion !== 'Pendiente') {
                return response()->json([
                    'message' => 'No se puede eliminar la suscripción porque la transacción ya ha sido confirmada.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // 2. Verificar que el estado de suscripción sea "Pendiente" o "Programado"
            $estadosPermitidos = ['Pendiente', 'Programado'];
            if (!in_array($suscripcion->estado_suscripcion, $estadosPermitidos)) {
                return response()->json([
                    'message' => 'No se puede eliminar la suscripción porque no cumple las condiciones requeridas. Solo se pueden eliminar suscripciones en estado Pendiente o Programado.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // 3. Verificar que no existan comprobantes emitidos asociados
            if ($suscripcion->comprobantes_usados > 0) {
                return response()->json([
                    'message' => 'No se puede eliminar la suscripción porque ya tiene comprobantes emitidos asociados.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Iniciar transacción para garantizar integridad
            DB::beginTransaction();

            try {
                // Registrar en auditoría antes de eliminar (usando la tabla de estado_audit)
                SuscripcionEstadoAudit::create([
                    'suscripcion_id' => $suscripcion->id,
                    'estado_anterior' => $suscripcion->estado_suscripcion,
                    'estado_nuevo' => 'ELIMINADO',
                    'tipo_transicion' => 'Manual',
                    'motivo' => 'Suscripción eliminada por usuario',
                    'user_id' => $currentUser->id,
                    'user_role' => $currentUser->role->value ?? $currentUser->role,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'created_at' => now(),
                ]);

                // Eliminar archivos asociados si existen
                if ($suscripcion->comprobante_pago) {
                    Storage::disk('public')->delete($suscripcion->comprobante_pago);
                }
                if ($suscripcion->factura) {
                    Storage::disk('public')->delete($suscripcion->factura);
                }
                if ($suscripcion->comprobante_comision) {
                    Storage::disk('public')->delete($suscripcion->comprobante_comision);
                }

                // Eliminar físicamente la suscripción (forceDelete para evitar soft delete)
                $suscripcion->forceDelete();

                DB::commit();

                Log::info('Suscripción eliminada', [
                    'suscripcion_id' => $id,
                    'emisor_id' => $emisorId,
                    'eliminado_por' => $currentUser->id,
                    'rol' => $currentUser->role,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'message' => '✅ Suscripción eliminada correctamente.'
                ], Response::HTTP_OK);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Suscripción no encontrada'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Error al eliminar suscripción', [
                'suscripcion_id' => $id,
                'emisor_id' => $emisorId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => '❌ Error al intentar eliminar la suscripción. Intente nuevamente.',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener historial de cambios de estado de una suscripción.
     */
    public function historialEstados($emisorId, $id)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();

            if (!$currentUser) {
                return response()->json([
                    'message' => 'No autenticado'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Solo admin puede ver el historial completo
            if ($currentUser->role !== UserRole::ADMINISTRADOR) {
                return response()->json([
                    'message' => 'No tienes permiso para ver el historial de estados'
                ], Response::HTTP_FORBIDDEN);
            }

            $suscripcion = Suscripcion::where('emisor_id', $emisorId)
                ->where('id', $id)
                ->firstOrFail();

            $estadoService = new SuscripcionEstadoService();
            $historial = $estadoService->getHistorialEstados($id);

            return response()->json([
                'message' => 'Historial de estados obtenido',
                'data' => [
                    'suscripcion_id' => $id,
                    'estado_actual' => $suscripcion->estado_suscripcion,
                    'historial' => $historial,
                ]
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Suscripción no encontrada'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener historial de estados',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Evaluar y actualizar estados automáticos de todas las suscripciones de un emisor.
     * Útil para sincronizar estados al ver la lista.
     */
    public function evaluarEstados($emisorId)
    {
        try {
            /** @var \App\Models\User|null $currentUser */
            $currentUser = Auth::user();

            if (!$currentUser) {
                return response()->json([
                    'message' => 'No autenticado'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Verificar acceso al emisor
            $emisor = Company::findOrFail($emisorId);
            if ($currentUser->role === UserRole::DISTRIBUIDOR) {
                if ($emisor->created_by_id !== $currentUser->id) {
                    return response()->json([
                        'message' => 'No tienes acceso a este emisor'
                    ], Response::HTTP_FORBIDDEN);
                }
            }

            $estadoService = new SuscripcionEstadoService();
            $actualizadas = $estadoService->evaluarSuscripcionesEmisor($emisorId);

            return response()->json([
                'message' => $actualizadas > 0 
                    ? "Se actualizaron {$actualizadas} suscripción(es)." 
                    : "Todos los estados están actualizados.",
                'data' => [
                    'suscripciones_actualizadas' => $actualizadas,
                ]
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Emisor no encontrado'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Error al evaluar estados de suscripciones', [
                'emisor_id' => $emisorId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error al evaluar estados',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
