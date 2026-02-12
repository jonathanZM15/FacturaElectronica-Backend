<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Establecimiento;
use App\Models\PuntoEmision;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Services\PermissionService;
use App\Enums\UserRole;
use App\Services\PuntoEmisionDisponibilidadService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Mail\EmailVerificationMail;
use App\Mail\PasswordChangeMail;

class UserController extends Controller
{
    /**
     * Listar usuarios con paginación, búsqueda y filtros
     * Respeta jerarquía de roles: admin ve todos, distribuidor ve sus creados, etc.
     */
    public function index(Request $request)
    {
        try {
            /** @var User|null $currentUser */
            $currentUser = Auth::user();
            
            // Parámetros de paginación y filtrado
            $page = max(1, (int)($request->input('page', 1)));
            $perPage = max(5, min(100, (int)($request->input('per_page', 20))));
            $search = trim($request->input('search', ''));
            $role = trim($request->input('role', ''));
            $sortBy = $request->input('sort_by', 'created_at');
            $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

            // Validar parámetros
            $validSortColumns = ['id', 'name', 'email', 'role', 'created_at', 'updated_at'];
            if (!in_array($sortBy, $validSortColumns)) {
                $sortBy = 'created_at';
            }

            // Construir query
            $query = User::query();

            // Filtrar según el rol del usuario actual
            if ($currentUser->role === UserRole::ADMINISTRADOR) {
                // Admin ve todos los usuarios
            } elseif ($currentUser->role === UserRole::DISTRIBUIDOR) {
                // Distribuidor ve: a sí mismo, los usuarios que creó, y los emisores bajo su cuenta
                $query->where(function ($q) use ($currentUser) {
                    $q->where('id', $currentUser->id)
                      ->orWhere('created_by_id', $currentUser->id)
                      ->orWhere('distribuidor_id', $currentUser->id);
                });
            } elseif ($currentUser->role === UserRole::EMISOR) {
                // Emisor ve: a sí mismo y los usuarios que creó (gerentes y cajeros)
                $query->where(function ($q) use ($currentUser) {
                    $q->where('id', $currentUser->id)
                      ->orWhere('created_by_id', $currentUser->id);
                });
            } elseif ($currentUser->role === UserRole::GERENTE) {
                // Gerente ve: a sí mismo y los cajeros que creó
                $query->where(function ($q) use ($currentUser) {
                    $q->where('id', $currentUser->id)
                      ->orWhere('created_by_id', $currentUser->id);
                });
            } else {
                // Cajero solo se ve a sí mismo
                $query->where('id', $currentUser->id);
            }

            // Filtro: búsqueda por nombre o email
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where(DB::raw('LOWER(name)'), 'like', '%' . strtolower($search) . '%')
                      ->orWhere(DB::raw('LOWER(email)'), 'like', '%' . strtolower($search) . '%');
                });
            }

            // Filtro: rol (validar que sea uno de los roles permitidos)
            if (!empty($role)) {
                try {
                    $roleEnum = UserRole::from($role);
                    $query->where('role', $roleEnum);
                } catch (\ValueError $e) {
                    // Rol inválido, ignorar
                }
            }

            // Ordenamiento
            $query->orderBy($sortBy, $sortDir);

            // Obtener datos paginados
            $users = $query->paginate($perPage, ['*'], 'page', $page);

            // Cargar información del creador para cada usuario
            $users->load('creador:id,nombres,apellidos,username,email,role');
            
            // Normalizar datos del creador en cada usuario
            $users->getCollection()->each(function ($user) {
                $creador = $user->creador;
                $creadorUsername = $creador?->username
                    ?: ($creador?->email ?? $creador?->cedula ?? null);

                if ($creador) {
                    $user->created_by_name = trim(($creador->nombres ?? '') . ' ' . ($creador->apellidos ?? ''));
                    $user->created_by_username = $creadorUsername;
                    $user->created_by_role = $creador->role?->value;
                } else {
                    $user->created_by_name = 'Sistema';
                    $user->created_by_username = null;
                    $user->created_by_role = null;
                }
            });

            Log::info('Usuarios listados', [
                'usuario_id' => $currentUser->id,
                'rol_usuario' => $currentUser->role->value,
                'total' => $users->total(),
                'search' => $search,
                'role_filter' => $role
            ]);

            return response()->json([
                'data' => $users->items(),
                'meta' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error listando usuarios', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error al listar usuarios'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener información detallada de un usuario específico
     * Valida que el usuario pueda ver el usuario solicitado según jerarquía
     */
    public function show(string $id)
    {
        try {
            // Validar que el ID es numérico
            if (!is_numeric($id)) {
                return response()->json(['message' => 'ID de usuario inválido'], Response::HTTP_BAD_REQUEST);
            }

            /** @var User|null $currentUser */
            $currentUser = Auth::user();
            $user = User::find($id);

            if (!$user) {
                Log::warning('Usuario no encontrado', [
                    'usuario_id' => $id,
                    'solicitante_id' => $currentUser->id
                ]);
                return response()->json(['message' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
            }

            // Validar permisos: ¿puede ver este usuario?
            $puedeVer = false;
            
            // Jerarquía de roles (de mayor a menor)
            $roleHierarchy = [
                'administrador' => 5,
                'distribuidor' => 4,
                'emisor' => 3,
                'gerente' => 2,
                'cajero' => 1
            ];
            
            $currentUserLevel = $roleHierarchy[$currentUser->role->value] ?? 0;
            $targetUserLevel = $roleHierarchy[$user->role->value] ?? 0;
            
            // 1. Puede verse a sí mismo
            if ($currentUser->id === $user->id) {
                $puedeVer = true;
            }
            // 2. Administrador puede ver a todos
            elseif ($currentUser->role === UserRole::ADMINISTRADOR) {
                $puedeVer = true;
            }
            // 3. Puede ver usuarios con rol inferior en la jerarquía
            elseif ($currentUserLevel > $targetUserLevel) {
                // Distribuidor puede ver emisores, gerentes y cajeros
                if ($currentUser->role === UserRole::DISTRIBUIDOR) {
                    $puedeVer = in_array($user->role->value, ['emisor', 'gerente', 'cajero']);
                }
                // Emisor puede ver gerentes y cajeros de su organización
                elseif ($currentUser->role === UserRole::EMISOR) {
                    $puedeVer = in_array($user->role->value, ['gerente', 'cajero']) &&
                               $user->emisor_id === $currentUser->id;
                }
                // Gerente puede ver cajeros del mismo emisor
                elseif ($currentUser->role === UserRole::GERENTE) {
                    $puedeVer = $user->role === UserRole::CAJERO &&
                               $user->emisor_id === $currentUser->emisor_id;
                }
            }
            // 4. Usuarios del mismo nivel pueden verse entre sí si pertenecen al mismo emisor
            elseif ($currentUserLevel === $targetUserLevel && $currentUser->emisor_id && $user->emisor_id) {
                $puedeVer = $currentUser->emisor_id === $user->emisor_id;
            }

            if (!$puedeVer) {
                Log::warning('Intento de acceso no autorizado a usuario', [
                    'usuario_id' => $id,
                    'solicitante_id' => $currentUser->id,
                    'rol_solicitante' => $currentUser->role->value,
                    'rol_objetivo' => $user->role->value,
                    'emisor_actual' => $currentUser->emisor_id,
                    'emisor_objetivo' => $user->emisor_id
                ]);
                return response()->json(['message' => 'No tienes permiso para ver este usuario'], Response::HTTP_FORBIDDEN);
            }

            // Cargar el usuario que creó este registro
            $user->load('creador:id,nombres,apellidos,username,email,cedula,role');
            
            // Agregar información completa del creador al objeto
            $creador = $user->creador;
            $creadorUsername = $creador?->username
                ?: ($creador?->email ?? $creador?->cedula ?? null);
            
            if ($creador) {
                $user->created_by_name = trim($creador->nombres . ' ' . $creador->apellidos);
                $user->created_by_username = $creadorUsername;
                $user->created_by_nombres = $creador->nombres;
                $user->created_by_apellidos = $creador->apellidos;
                $user->created_by_role = $creador->role?->value;
            } else {
                $user->created_by_name = 'Sistema';
                $user->created_by_username = null;
                $user->created_by_nombres = null;
                $user->created_by_apellidos = null;
                $user->created_by_role = null;
            }

            Log::info('Usuario consultado', [
                'usuario_id' => $id,
                'solicitante_id' => $currentUser->id
            ]);

            return response()->json(['data' => $user]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo usuario', ['error' => $e->getMessage(), 'usuario_id' => $id]);
            return response()->json(['message' => 'Error al obtener usuario'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Crear un nuevo usuario
     * Valida que el usuario actual pueda crear el rol solicitado
     */
    public function store(StoreUserRequest $request)
    {
        try {
            /** @var User|null $currentUser */
            $currentUser = Auth::user();
            $validated = $request->validated();

            // Los permisos fueron validados en StoreUserRequest::authorize()
            // Aquí solo creamos el usuario

            // Verificar que el email no exista (doble validación)
            if (User::where('email', $validated['email'])->exists()) {
                return response()->json([
                    'message' => 'El email ya está registrado',
                    'errors' => ['email' => ['El email ya existe en el sistema']]
                ], Response::HTTP_CONFLICT);
            }

            // Generar contraseña temporal si no se proporciona
            $password = $validated['password'] ?? $this->generateTemporaryPassword();

            // Determinar la relación según el rol a crear
            $roleACrear = UserRole::from($validated['role']);
            $distribuidor_id = null;
            $emisor_id = null;

            // Llenar referencias según jerarquía
            if ($currentUser->role === UserRole::ADMINISTRADOR) {
                // Admin puede crear cualquier rol, sin referencias especiales
                // Pero si se proporciona distribuidor_id, usarlo
                if (isset($validated['distribuidor_id'])) {
                    $distribuidor_id = $validated['distribuidor_id'];
                }
            } elseif ($currentUser->role === UserRole::DISTRIBUIDOR) {
                // Distribuidor se asigna a sí mismo
                $distribuidor_id = $currentUser->id;
            } elseif ($currentUser->role === UserRole::EMISOR) {
                // Emisor se asigna a sí mismo
                $emisor_id = $currentUser->id;
            } elseif ($currentUser->role === UserRole::GERENTE) {
                // Gerente se asigna a sí mismo
                $emisor_id = $currentUser->emisor_id; // Hereda el emisor del gerente
            }

            // Crear el usuario
            $user = User::create([
                'cedula' => $validated['cedula'],
                'nombres' => $validated['nombres'],
                'apellidos' => $validated['apellidos'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => Hash::make($password),
                'role' => $roleACrear->value, // Usar el valor del Enum
                'created_by_id' => $currentUser->id,
                'distribuidor_id' => $distribuidor_id,
                'emisor_id' => $emisor_id,
                // Estado por defecto: 'nuevo'. Excepción: admin@factura.local es siempre 'activo'
                'estado' => ($validated['email'] ?? '') === 'admin@factura.local'
                    ? 'activo'
                    : ($validated['estado'] ?? 'nuevo'),
                'establecimientos_ids' => isset($validated['establecimientos_ids']) 
                    ? json_encode($validated['establecimientos_ids']) 
                    : null,
            ]);

            Log::info('Usuario creado', [
                'nuevo_usuario_id' => $user->id,
                'email' => $user->email,
                'cedula' => $user->cedula,
                'username' => $user->username,
                'role' => $user->role->value,
                'created_by_id' => $currentUser->id,
                'rol_creador' => $currentUser->role->value
            ]);

            // Enviar correo de verificación si el usuario no es admin@factura.local
            if ($user->email !== 'admin@factura.local' && $user->estado === 'nuevo') {
                try {
                    $this->sendVerificationEmail($user);
                    Log::info('Correo de verificación enviado', ['usuario_id' => $user->id, 'email' => $user->email]);
                } catch (\Exception $emailError) {
                    Log::error('Error enviando correo de verificación', [
                        'usuario_id' => $user->id,
                        'error' => $emailError->getMessage()
                    ]);
                    // No fallar la creación del usuario si falla el email
                }
            }

            return response()->json([
                'message' => 'Usuario creado exitosamente',
                'data' => [
                    'id' => $user->id,
                    'cedula' => $user->cedula,
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error('Error creando usuario', [
                'error' => $e->getMessage(),
                'usuario_id' => $currentUser->id ?? null
            ]);
            return response()->json([
                'message' => 'Error al crear usuario'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Actualizar información de un usuario
     * Valida que el usuario actual pueda actualizar el usuario objetivo
     */
    public function update(string $id, UpdateUserRequest $request)
    {
        try {
            // Validar que el ID es numérico
            if (!is_numeric($id)) {
                return response()->json(['message' => 'ID de usuario inválido'], Response::HTTP_BAD_REQUEST);
            }

            /** @var User|null $currentUser */
            $currentUser = Auth::user();
            
            // Verificar que el usuario actual sea administrador o distribuidor
            if (!in_array($currentUser->role->value, ['administrador', 'distribuidor'])) {
                Log::warning('Intento de edición sin permisos (rol no permitido)', [
                    'usuario_a_editar' => $id,
                    'usuario_actual_id' => $currentUser->id,
                    'rol_actual' => $currentUser->role->value
                ]);
                return response()->json([
                    'message' => 'Solo administrador o distribuidor pueden editar usuarios'
                ], Response::HTTP_FORBIDDEN);
            }

            $user = User::find($id);

            if (!$user) {
                Log::warning('Usuario no encontrado para actualizar', [
                    'usuario_id' => $id,
                    'usuario_actual_id' => $currentUser->id
                ]);
                return response()->json(['message' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
            }

            // Validar que puede gestionar este usuario
            if (!PermissionService::puedeGestionarUsuario($currentUser, $user)) {
                Log::warning('Intento de actualización no autorizada', [
                    'usuario_objetivo_id' => $id,
                    'usuario_actual_id' => $currentUser->id,
                    'rol_actual' => $currentUser->role->value
                ]);
                return response()->json(['message' => 'No tienes permiso para actualizar este usuario'], Response::HTTP_FORBIDDEN);
            }

            $validated = $request->validated();
            $cambios = [];

            // Actualizar cédula si se proporciona
            if (isset($validated['cedula'])) {
                if (User::where('cedula', $validated['cedula'])->where('id', '!=', $id)->exists()) {
                    return response()->json([
                        'message' => 'La cédula ya está registrada',
                        'errors' => ['cedula' => ['La cédula ya existe en el sistema']]
                    ], Response::HTTP_CONFLICT);
                }
                $user->cedula = $validated['cedula'];
                $cambios['cedula'] = $validated['cedula'];
            }

            // Actualizar nombres si se proporciona
            if (isset($validated['nombres'])) {
                $user->nombres = $validated['nombres'];
                $cambios['nombres'] = $validated['nombres'];
            }

            // Actualizar apellidos si se proporciona
            if (isset($validated['apellidos'])) {
                $user->apellidos = $validated['apellidos'];
                $cambios['apellidos'] = $validated['apellidos'];
            }

            // Actualizar username si se proporciona
            if (isset($validated['username'])) {
                if (User::where('username', $validated['username'])->where('id', '!=', $id)->exists()) {
                    return response()->json([
                        'message' => 'El username ya está registrado',
                        'errors' => ['username' => ['El username ya existe en el sistema']]
                    ], Response::HTTP_CONFLICT);
                }
                $user->username = $validated['username'];
                $cambios['username'] = $validated['username'];
            }

            // Actualizar email si se proporciona (debe ser único)
            if (isset($validated['email'])) {
                if (User::where('email', $validated['email'])->where('id', '!=', $id)->exists()) {
                    return response()->json([
                        'message' => 'El email ya está registrado',
                        'errors' => ['email' => ['El email ya existe en el sistema']]
                    ], Response::HTTP_CONFLICT);
                }
                $user->email = $validated['email'];
                $cambios['email'] = $validated['email'];
            }

            // Actualizar rol si se proporciona
            if (isset($validated['role'])) {
                $newRole = UserRole::from($validated['role']);

                // Validar que no se elimine el último admin
                if ($newRole !== UserRole::ADMINISTRADOR && $user->role === UserRole::ADMINISTRADOR) {
                    $adminCount = User::where('role', UserRole::ADMINISTRADOR)->count();
                    if ($adminCount <= 1) {
                        Log::warning('Intento de cambiar rol del último admin', [
                            'usuario_id' => $id,
                            'usuario_actual_id' => $currentUser->id
                        ]);
                        return response()->json([
                            'message' => 'No se puede cambiar el rol del único administrador del sistema'
                        ], Response::HTTP_CONFLICT);
                    }
                }
                $user->role = $newRole->value;  // Usar el valor del enum, no el enum mismo
                $cambios['role'] = $newRole->value;
            }

            // Actualizar estado si se proporciona
            if (isset($validated['estado'])) {
                // El usuario admin@factura.local no puede cambiar su estado y siempre es 'activo'
                if ($user->email === 'admin@factura.local' && $validated['estado'] !== 'activo') {
                    return response()->json([
                        'message' => 'El estado del usuario admin@factura.local no puede modificarse y siempre es Activo'
                    ], Response::HTTP_CONFLICT);
                }
                $user->estado = $validated['estado'];
                $cambios['estado'] = $validated['estado'];
            }

            // Actualizar establecimientos_ids si se proporciona y es gerente
            if (isset($validated['establecimientos_ids']) && $user->role === UserRole::GERENTE) {
                $user->establecimientos_ids = $validated['establecimientos_ids'];
            }

            $user->save();

            Log::info('Usuario actualizado', [
                'usuario_id' => $id,
                'cambios' => $cambios,
                'usuario_actual_id' => $currentUser->id,
                'rol_actual' => $currentUser->role->value
            ]);

            return response()->json([
                'message' => 'Usuario actualizado exitosamente',
                'data' => [
                    'id' => $user->id,
                    'cedula' => $user->cedula,
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'estado' => $user->estado,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error actualizando usuario', [
                'error' => $e->getMessage(),
                'usuario_id' => $id,
                'usuario_actual_id' => $currentUser->id ?? null
            ]);
            return response()->json([
                'message' => 'Error al actualizar usuario'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Eliminar un usuario
     * Requiere contraseña del usuario actual y valida jerarquía
     */
    public function destroy(string $id, Request $request)
    {
        try {
            // Validar que el ID es numérico
            if (!is_numeric($id)) {
                return response()->json(['message' => 'ID de usuario inválido'], Response::HTTP_BAD_REQUEST);
            }

            // Validar que se proporciona contraseña
            $request->validate([
                'password' => 'required|string'
            ], [
                'password.required' => 'La contraseña del usuario actual es requerida'
            ]);

            /** @var User|null $currentUser */
            $currentUser = Auth::user();
            $password = $request->input('password');

            // Verificar que el usuario actual sea administrador o distribuidor
            if (!in_array($currentUser->role->value, ['administrador', 'distribuidor'])) {
                Log::warning('Intento de eliminación sin permisos (rol no permitido)', [
                    'usuario_a_eliminar' => $id,
                    'usuario_actual_id' => $currentUser->id,
                    'rol_actual' => $currentUser->role->value
                ]);
                return response()->json([
                    'message' => 'Solo administrador o distribuidor pueden eliminar usuarios'
                ], Response::HTTP_FORBIDDEN);
            }

            // Verificar contraseña del usuario actual
            if (!Hash::check($password, $currentUser->password)) {
                Log::warning('Intento de eliminación con contraseña incorrecta', [
                    'usuario_a_eliminar' => $id,
                    'usuario_actual_id' => $currentUser->id
                ]);
                return response()->json([
                    'message' => 'Contraseña incorrecta'
                ], Response::HTTP_UNAUTHORIZED);
            }

            $user = User::find($id);

            if (!$user) {
                Log::warning('Usuario no encontrado para eliminar', [
                    'usuario_id' => $id,
                    'usuario_actual_id' => $currentUser->id
                ]);
                return response()->json(['message' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
            }

            // Validar que el usuario esté en estado "Nuevo"
            if ($user->estado !== 'nuevo') {
                Log::warning('Intento de eliminación de usuario no nuevo', [
                    'usuario_id' => $id,
                    'estado' => $user->estado,
                    'usuario_actual_id' => $currentUser->id
                ]);
                return response()->json([
                    'message' => 'Solo se pueden eliminar usuarios en estado "Nuevo". Este usuario está en estado "' . $user->estado . '". Para cambiar su estado, utiliza la opción editar.'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validar que puede gestionar este usuario
            if (!PermissionService::puedeGestionarUsuario($currentUser, $user)) {
                Log::warning('Intento de eliminación no autorizada', [
                    'usuario_objetivo_id' => $id,
                    'usuario_actual_id' => $currentUser->id,
                    'rol_actual' => $currentUser->role->value
                ]);
                return response()->json(['message' => 'No tienes permiso para eliminar este usuario'], Response::HTTP_FORBIDDEN);
            }

            // Validar que no sea el último admin
            if ($user->role === UserRole::ADMINISTRADOR) {
                $adminCount = User::where('role', UserRole::ADMINISTRADOR)->count();
                if ($adminCount <= 1) {
                    Log::warning('Intento de eliminar último admin', [
                        'usuario_id' => $id,
                        'usuario_actual_id' => $currentUser->id
                    ]);
                    return response()->json([
                        'message' => 'No se puede eliminar el único administrador del sistema'
                    ], Response::HTTP_CONFLICT);
                }
            }

            // Validar que el usuario no se elimine a sí mismo
            if ($user->id === $currentUser->id) {
                Log::warning('Intento de auto-eliminación', ['usuario_id' => $currentUser->id]);
                return response()->json([
                    'message' => 'No puedes eliminar tu propia cuenta'
                ], Response::HTTP_CONFLICT);
            }

            $user->delete();

            // Si el usuario tenía puntos asociados, recalcular disponibilidad
            try {
                $companyId = (int) ($user->emisor_id ?? 0);
                $puntos = $user->puntos_emision_ids;
                if (is_string($puntos)) {
                    $puntos = json_decode($puntos, true) ?? [];
                }
                if ($companyId > 0 && is_array($puntos) && !empty($puntos)) {
                    (new PuntoEmisionDisponibilidadService())->recalculate($companyId, $puntos, (int) $user->id);
                }
            } catch (\Exception $e) {
                Log::warning('No se pudo recalcular disponibilidad de puntos al eliminar usuario', [
                    'usuario_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }

            Log::info('Usuario eliminado', [
                'usuario_eliminado_id' => $id,
                'usuario_eliminado_email' => $user->email,
                'usuario_eliminado_role' => $user->role->value,
                'estado_eliminado' => $user->estado,
                'usuario_actual_id' => $currentUser->id,
                'rol_actual' => $currentUser->role->value
            ]);

            return response()->json([
                'message' => 'Usuario eliminado exitosamente'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validación fallida',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Error eliminando usuario', [
                'error' => $e->getMessage(),
                'usuario_id' => $id,
                'usuario_actual_id' => $currentUser->id ?? null
            ]);
            return response()->json([
                'message' => 'Error al eliminar usuario'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Listar usuarios asociados a un emisor específico
     * HU 2: Registro de usuarios asociados a un emisor
     * 
     * Restricciones de visibilidad:
     * - Administrador: Puede ver todos los usuarios del emisor
     * - Distribuidor: Solo puede ver usuarios de emisores que él registró
     * - Emisor: Solo puede ver gerentes y cajeros de su emisor
     * - Gerente: Solo puede ver cajeros asociados a sus establecimientos
     * - Cajero: Solo puede verse a sí mismo
     */
    public function indexByEmisor(Request $request, $id)
    {
        try {
            /** @var User|null $currentUser */
            $currentUser = Auth::user();
            
            // Validar permisos base
            $canView = false;
            $roleFilter = null; // Filtro de roles permitidos
            $establishmentFilter = null; // Filtro de establecimientos
            $limitedToSelf = false;
            $postFilterByEstablishment = false; // Inicializar aquí
            
            if ($currentUser->role === UserRole::ADMINISTRADOR) {
                // Administrador puede ver todos los usuarios del emisor
                $canView = true;
            } elseif ($currentUser->role === UserRole::DISTRIBUIDOR) {
                // Distribuidor: verificar que el emisor fue creado por él
                $emisor = \App\Models\Company::find($id);
                if ($emisor && $emisor->created_by == $currentUser->id) {
                    $canView = true;
                }
            } elseif ($currentUser->role === UserRole::EMISOR && $currentUser->emisor_id == $id) {
                // Emisor solo ve gerentes y cajeros de su emisor
                $canView = true;
                $roleFilter = ['gerente', 'cajero'];
            } elseif ($currentUser->role === UserRole::GERENTE && $currentUser->emisor_id == $id) {
                // Gerente solo ve cajeros asociados a sus establecimientos
                $canView = true;
                $roleFilter = ['cajero'];
                // Obtener los establecimientos del gerente
                $gerenteEstablecimientos = $currentUser->establecimientos_ids;
                if (is_string($gerenteEstablecimientos)) {
                    $gerenteEstablecimientos = json_decode($gerenteEstablecimientos, true);
                }
                $establishmentFilter = $gerenteEstablecimientos ?: [];
            } elseif ($currentUser->role === UserRole::CAJERO && $currentUser->emisor_id == $id) {
                // Cajero solo puede verse a sí mismo
                $canView = true;
                $limitedToSelf = true;
            }
            
            if (!$canView) {
                return response()->json([
                    'message' => 'No tienes permisos para ver estos usuarios'
                ], Response::HTTP_FORBIDDEN);
            }

            // Parámetros de paginación
            $page = max(1, (int)($request->input('page', 1)));
            $perPage = max(10, min(100, (int)($request->input('per_page', 20))));
            $search = trim($request->input('search', ''));

            // Listar usuarios del emisor
            $query = User::where('emisor_id', $id)
                ->with('creador:id,cedula,nombres,apellidos,username,email,role');

            // Excluir al usuario actual para roles emisor, gerente y cajero
            // (estos usuarios no deben verse a sí mismos en la lista)
            if (in_array($currentUser->role, [UserRole::EMISOR, UserRole::GERENTE, UserRole::CAJERO])) {
                $query->where('id', '!=', $currentUser->id);
            }

            // Aplicar filtro de usuario limitado a sí mismo (ya no aplica porque cajero no ve nada)
            if ($limitedToSelf) {
                // Cajero no debe ver a nadie (ni a sí mismo)
                $query->where('id', 0); // Forzar resultado vacío
            }
            
            // Aplicar filtro de roles si existe (excepto para gerentes que tienen filtro especial)
            if ($roleFilter !== null && $establishmentFilter === null) {
                $query->whereIn('role', $roleFilter);
            }
            
            // Aplicar filtro de establecimientos para gerentes
            // Para gerentes: solo cajeros con establecimientos compartidos (ya no se incluye a sí mismo)
            if ($establishmentFilter !== null && !empty($establishmentFilter)) {
                $postFilterByEstablishment = true;
                // Solo cajeros (el filtro fino de establecimientos se hace después)
                $query->where('role', 'cajero');
            }

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where(DB::raw('LOWER(cedula)'), 'like', '%' . strtolower($search) . '%')
                      ->orWhere(DB::raw('LOWER(nombres)'), 'like', '%' . strtolower($search) . '%')
                      ->orWhere(DB::raw('LOWER(apellidos)'), 'like', '%' . strtolower($search) . '%')
                      ->orWhere(DB::raw('LOWER(username)'), 'like', '%' . strtolower($search) . '%')
                      ->orWhere(DB::raw('LOWER(email)'), 'like', '%' . strtolower($search) . '%');
                });
            }

            $users = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

            // Mapear datos para incluir información del creador y establecimientos/puntos de emisión
            $data = $users->items();
            
            // Filtrar cajeros por establecimientos compartidos con el gerente (post-filtrado)
            if ($postFilterByEstablishment && !empty($establishmentFilter)) {
                $data = array_filter($data, function ($user) use ($establishmentFilter, $currentUser) {
                    // Para cajeros, verificar si comparten al menos un establecimiento
                    $userEstIds = $user->establecimientos_ids;
                    if (is_string($userEstIds)) {
                        $userEstIds = json_decode($userEstIds, true) ?? [];
                    }
                    if (!is_array($userEstIds)) {
                        $userEstIds = [];
                    }
                    // Verificar intersección
                    $intersection = array_intersect($userEstIds, $establishmentFilter);
                    return count($intersection) > 0;
                });
                $data = array_values($data); // Re-indexar el array
            }
            
            $data = array_map(function ($user) {
                $creador = $user->creador;
                $creadorUsername = $creador?->username
                    ?: ($creador?->email ?? $creador?->cedula ?? null);

                return array_merge($user->toArray(), [
                    'created_by_id' => $user->created_by_id,
                    'created_by_username' => $creadorUsername,
                    'created_by_nombres' => $creador?->nombres,
                    'created_by_apellidos' => $creador?->apellidos,
                    'created_by_role' => $creador?->role?->value,
                    'establecimientos' => $user->establecimientos,
                    'puntos_emision' => $user->puntos_emision,
                ]);
            }, $data);

            Log::info('Usuarios del emisor listados', [
                'usuario_actual_id' => $currentUser->id,
                'rol' => $currentUser->role->value,
                'emisor_id' => $id,
                'total' => count($data),
                'roleFilter' => $roleFilter,
                'establishmentFilter' => $establishmentFilter,
                'postFiltered' => $postFilterByEstablishment
            ]);

            // Si hubo post-filtrado, ajustar el total
            $totalCount = $postFilterByEstablishment ? count($data) : $users->total();

            return response()->json([
                'data' => $data,
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'total' => $totalCount,
                    'per_page' => $users->perPage(),
                    'last_page' => $postFilterByEstablishment ? 1 : $users->lastPage(),
                    'from' => count($data) > 0 ? 1 : 0,
                    'to' => count($data),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error listando usuarios del emisor', [
                'error' => $e->getMessage(),
                'emisor_id' => $id
            ]);
            return response()->json([
                'message' => 'Error al listar usuarios'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Crear usuario asociado a un emisor
     * HU 2: Registro de usuarios asociados a un emisor
     */
    public function storeByEmisor(StoreUserRequest $request, $id)
    {
        try {
            // Asegurar que $id es un entero
            $id = (int) $id;
            
            /** @var User|null $currentUser */
            $currentUser = Auth::user();
            
            // Validar permisos: solo Administrador, Distribuidor, Emisor y Gerente pueden crear usuarios
            // Administrador y Distribuidor pueden crear en cualquier emisor
            // Emisor y Gerente solo pueden crear en su propio emisor
            $canCreate = false;
            
            if ($currentUser->role === UserRole::ADMINISTRADOR || $currentUser->role === UserRole::DISTRIBUIDOR) {
                // Administrador y Distribuidor pueden crear en cualquier emisor
                $canCreate = true;
            } elseif (($currentUser->role === UserRole::EMISOR || $currentUser->role === UserRole::GERENTE) && 
                      $currentUser->emisor_id == $id) {
                // Emisor y Gerente solo en su propio emisor
                $canCreate = true;
            }
            
            if (!$canCreate) {
                return response()->json([
                    'message' => 'No tienes permisos para crear usuarios en este emisor'
                ], Response::HTTP_FORBIDDEN);
            }

            // Validar jerarquía de roles
            $roleToCreate = UserRole::from($request->input('role'));
            $permissionService = new PermissionService();
            
            if (!$permissionService->puedoCrearRol($currentUser, $roleToCreate->value)) {
                return response()->json([
                    'message' => 'No tienes permisos para crear usuarios con este rol'
                ], Response::HTTP_FORBIDDEN);
            }

            // Reglas de negocio: para EMISOR/GERENTE/CAJERO es obligatorio
            // seleccionar 1 punto de emisión por cada establecimiento asignado.
            if (in_array($roleToCreate, [UserRole::EMISOR, UserRole::GERENTE, UserRole::CAJERO], true)) {
                $establecimientosIds = $request->input('establecimientos_ids', []);
                $establecimientosIds = is_array($establecimientosIds) ? $establecimientosIds : [];
                $establecimientosIds = array_values(array_unique(array_map('intval', $establecimientosIds)));

                // Para EMISOR, si no llegan establecimientos_ids, asociar a todos los ABIERTO del emisor.
                if ($roleToCreate === UserRole::EMISOR && empty($establecimientosIds)) {
                    $establecimientosIds = Establecimiento::query()
                        ->where('company_id', (int) $id)
                        ->where('estado', 'ABIERTO')
                        ->pluck('id')
                        ->map(fn ($v) => (int) $v)
                        ->values()
                        ->all();
                }

                if (empty($establecimientosIds)) {
                    throw ValidationException::withMessages([
                        'establecimientos_ids' => ['Debe seleccionar al menos un establecimiento.'],
                    ]);
                }

                // Validar que los establecimientos pertenezcan al emisor y estén ABIERTO
                $validEstIds = Establecimiento::query()
                    ->where('company_id', (int) $id)
                    ->where('estado', 'ABIERTO')
                    ->whereIn('id', $establecimientosIds)
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->values()
                    ->all();

                if (count($validEstIds) !== count($establecimientosIds)) {
                    throw ValidationException::withMessages([
                        'establecimientos_ids' => ['Hay establecimientos inválidos o no disponibles (deben estar ABIERTO).'],
                    ]);
                }

                // Solo exigir punto si el establecimiento tiene al menos uno disponible (ACTIVO + LIBRE)
                $requiredEstIds = PuntoEmision::query()
                    ->where('company_id', (int) $id)
                    ->whereIn('establecimiento_id', $establecimientosIds)
                    ->where('estado', 'ACTIVO')
                    ->where('estado_disponibilidad', PuntoEmisionDisponibilidadService::LIBRE)
                    ->select('establecimiento_id')
                    ->distinct()
                    ->pluck('establecimiento_id')
                    ->map(fn ($v) => (int) $v)
                    ->values()
                    ->all();

                $puntosIds = $request->input('puntos_emision_ids', []);
                $puntosIds = is_array($puntosIds) ? $puntosIds : [];
                $puntosIds = array_values(array_unique(array_map('intval', $puntosIds)));

                if (count($puntosIds) !== count($requiredEstIds)) {
                    throw ValidationException::withMessages([
                        'puntos_emision_ids' => ['Debe asignar 1 punto de emisión por cada establecimiento que tenga puntos disponibles.'],
                    ]);
                }

                $puntos = PuntoEmision::query()
                    ->where('company_id', (int) $id)
                    ->whereIn('id', $puntosIds)
                    ->where('estado', 'ACTIVO')
                    ->where('estado_disponibilidad', PuntoEmisionDisponibilidadService::LIBRE)
                    ->get(['id', 'establecimiento_id']);

                if ($puntos->count() !== count($puntosIds)) {
                    throw ValidationException::withMessages([
                        'puntos_emision_ids' => ['Hay puntos inválidos/no disponibles (deben estar ACTIVO y LIBRE).'],
                    ]);
                }

                $byEst = [];
                foreach ($puntos as $p) {
                    $estId = (int) $p->establecimiento_id;
                    if (!in_array($estId, $establecimientosIds, true)) {
                        throw ValidationException::withMessages([
                            'puntos_emision_ids' => ['Hay puntos que no pertenecen a los establecimientos seleccionados.'],
                        ]);
                    }
                    if (isset($byEst[$estId])) {
                        throw ValidationException::withMessages([
                            'puntos_emision_ids' => ['No se permite más de un punto para el mismo establecimiento.'],
                        ]);
                    }
                    $byEst[$estId] = (int) $p->id;
                }

                foreach ($requiredEstIds as $estId) {
                    if (!isset($byEst[(int) $estId])) {
                        throw ValidationException::withMessages([
                            'puntos_emision_ids' => ['Debe asignar un punto de emisión para cada establecimiento que tenga puntos disponibles.'],
                        ]);
                    }
                }

                // Sobrescribir los valores normalizados para la creación
                $request->merge([
                    'establecimientos_ids' => $establecimientosIds,
                    'puntos_emision_ids' => $puntosIds,
                ]);
            }

            DB::beginTransaction();

            // Generar contraseña temporal si no se proporciona
            $password = $request->input('password') ?? $this->generateTemporaryPassword();

            // Crear usuario con datos del emisor
            $user = User::create([
                'cedula' => $request->input('cedula'),
                'nombres' => $request->input('nombres'),
                'apellidos' => $request->input('apellidos'),
                'username' => $request->input('username'),
                'email' => $request->input('email'),
                'password' => Hash::make($password),
                'role' => UserRole::from($request->input('role')),
                'estado' => ($request->input('email') ?? '') === 'admin@factura.local' ? 'activo' : 'nuevo',
                'created_by_id' => $currentUser->id,
                'emisor_id' => $id,
                'establecimientos_ids' => json_encode($request->input('establecimientos_ids', [])),
            ]);

            // Si proporciona puntos de emisión, almacenarlos
            if ($request->has('puntos_emision_ids')) {
                $puntos = $request->input('puntos_emision_ids', []);
                $user->puntos_emision_ids = $puntos;
                $user->save();

                // Gestión interna: marcar OCUPADO los puntos asociados
                (new PuntoEmisionDisponibilidadService())->markOcupado((int) $id, $puntos);
            }

            DB::commit();

            // Recargar el usuario con relación del creador
            $user->load('creador:id,cedula,nombres,apellidos,username,email,role');

            Log::info('Usuario del emisor creado', [
                'nuevo_usuario_id' => $user->id,
                'cedula' => $user->cedula,
                'username' => $user->username,
                'rol' => $user->role->value,
                'emisor_id' => $id,
                'creado_por_id' => $currentUser->id,
                'rol_creador' => $currentUser->role->value,
                'establecimientos' => $request->input('establecimientos_ids', []),
                'puntos_emision' => $request->input('puntos_emision_ids', [])
            ]);

            // Enviar correo de verificación si el usuario no es admin@factura.local
            if ($user->email !== 'admin@factura.local' && $user->estado === 'nuevo') {
                try {
                    $this->sendVerificationEmail($user);
                    Log::info('Correo de verificación enviado', ['usuario_id' => $user->id, 'email' => $user->email]);
                } catch (\Exception $emailError) {
                    Log::error('Error enviando correo de verificación', [
                        'usuario_id' => $user->id,
                        'error' => $emailError->getMessage()
                    ]);
                    // No fallar la creación del usuario si falla el email
                }
            }

            // Mapear datos con información del creador
            $creador = $user->creador;
            $creadorUsername = $creador?->username
                ?: ($creador?->email ?? $creador?->cedula ?? null);

            $userData = array_merge($user->toArray(), [
                'created_by_id' => $user->created_by_id,
                'created_by_username' => $creadorUsername,
                'created_by_nombres' => $creador?->nombres,
                'created_by_apellidos' => $creador?->apellidos,
                'created_by_role' => $creador?->role?->value,
                'establecimientos' => $user->establecimientos,
                'puntos_emision' => $user->puntos_emision,
            ]);

            return response()->json([
                'message' => 'Usuario creado exitosamente',
                'data' => $userData
            ], Response::HTTP_CREATED);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validación fallida',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creando usuario del emisor', [
                'error' => $e->getMessage(),
                'emisor_id' => $id,
                'usuario_actual_id' => $currentUser->id ?? null
            ]);
            return response()->json([
                'message' => 'Error al crear usuario'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener detalles de un usuario del emisor
     * HU 2: Registro de usuarios asociados a un emisor
     */
    public function showByEmisor(Request $request, $id, $usuario)
    {
        try {
            /** @var User|null $currentUser */
            $currentUser = Auth::user();
            
            // Obtener el usuario objetivo
            $user = User::find($usuario);
            
            if (!$user) {
                return response()->json([
                    'message' => 'Usuario no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Validar permisos
            $canView = false;
            
            // Un usuario puede verse a sí mismo
            if ($currentUser->id == $usuario) {
                $canView = true;
            }
            // Administrador puede ver cualquier usuario
            elseif ($currentUser->role === UserRole::ADMINISTRADOR) {
                $canView = true;
            }
            // Distribuidor puede ver usuarios del emisor
            elseif ($currentUser->role === UserRole::DISTRIBUIDOR) {
                $canView = true;
            }
            // Emisor puede ver usuarios de su organización (emisor_id == id del emisor en la URL)
            elseif ($currentUser->role === UserRole::EMISOR && $currentUser->emisor_id == $id) {
                $canView = true;
            }
            // Gerente puede ver usuarios del mismo emisor
            elseif ($currentUser->role === UserRole::GERENTE && $currentUser->emisor_id == $id) {
                $canView = true;
            }
            // Cajero puede ver otros usuarios del mismo emisor (solo lectura)
            elseif ($currentUser->role === UserRole::CAJERO && $currentUser->emisor_id == $id) {
                $canView = true;
            }
            
            if (!$canView) {
                Log::warning('Intento de acceso no autorizado a usuario del emisor', [
                    'usuario_objetivo' => $usuario,
                    'emisor_id' => $id,
                    'solicitante_id' => $currentUser->id,
                    'rol_solicitante' => $currentUser->role->value
                ]);
                return response()->json([
                    'message' => 'No tienes permisos para ver este usuario'
                ], Response::HTTP_FORBIDDEN);
            }

            // Verificar que el usuario pertenece al emisor
            if ($user->emisor_id != $id && $user->id != $id) {
                return response()->json([
                    'message' => 'Usuario no encontrado en este emisor'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Cargar información del creador
            $user->load('creador:id,nombres,apellidos,username,email,cedula,role');
            
            $creador = $user->creador;
            $creadorUsername = $creador?->username
                ?: ($creador?->email ?? $creador?->cedula ?? null);
            
            if ($creador) {
                $user->created_by_name = trim($creador->nombres . ' ' . $creador->apellidos);
                $user->created_by_username = $creadorUsername;
                $user->created_by_nombres = $creador->nombres;
                $user->created_by_apellidos = $creador->apellidos;
                $user->created_by_role = $creador->role?->value;
            } else {
                $user->created_by_name = 'Sistema';
                $user->created_by_username = null;
                $user->created_by_nombres = null;
                $user->created_by_apellidos = null;
                $user->created_by_role = null;
            }

            return response()->json([
                'data' => $user
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo usuario del emisor', [
                'error' => $e->getMessage(),
                'emisor_id' => $id,
                'usuario_id' => $usuario
            ]);
            return response()->json([
                'message' => 'Error al obtener usuario'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Actualizar usuario del emisor
     * HU 2: Registro de usuarios asociados a un emisor
     * 
     * Restricciones:
     * - Administrador: Puede actualizar cualquier usuario
     * - Distribuidor: Solo usuarios de emisores que él registró
     * - Emisor: Solo gerentes y cajeros de su emisor
     * - Gerente: Solo cajeros asociados a sus establecimientos (y a sí mismo)
     */
    public function updateByEmisor(UpdateUserRequest $request, $id, $usuario)
    {
        try {
            /** @var User|null $currentUser */
            $currentUser = Auth::user();
            
            $user = User::find($usuario);
            
            if (!$user || ($user->emisor_id != $id && $user->id != $id)) {
                return response()->json([
                    'message' => 'Usuario no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Validar permisos según jerarquía
            $canUpdate = false;
            
            // Un usuario puede actualizarse a sí mismo
            if ($currentUser->id == $usuario) {
                $canUpdate = true;
            }
            // Administrador puede actualizar cualquier usuario
            elseif ($currentUser->role === UserRole::ADMINISTRADOR) {
                $canUpdate = true;
            }
            // Distribuidor: verificar que el emisor fue creado por él
            elseif ($currentUser->role === UserRole::DISTRIBUIDOR) {
                $emisor = \App\Models\Company::find($id);
                $canUpdate = $emisor && $emisor->created_by == $currentUser->id;
            }
            // Emisor puede actualizar gerentes y cajeros de su emisor
            elseif ($currentUser->role === UserRole::EMISOR && $currentUser->emisor_id == $id) {
                $canUpdate = in_array($user->role->value, ['gerente', 'cajero']);
            }
            // Gerente puede actualizar cajeros asociados a sus establecimientos
            elseif ($currentUser->role === UserRole::GERENTE && $currentUser->emisor_id == $id) {
                if ($user->role === UserRole::CAJERO) {
                    // Verificar establecimientos en común
                    $gerenteEsts = json_decode($currentUser->establecimientos_ids ?? '[]', true);
                    $cajeroEsts = json_decode($user->establecimientos_ids ?? '[]', true);
                    $canUpdate = !empty(array_intersect($gerenteEsts, $cajeroEsts));
                }
            }
            
            if (!$canUpdate) {
                return response()->json([
                    'message' => 'No tienes permisos para actualizar este usuario'
                ], Response::HTTP_FORBIDDEN);
            }

            // Reglas de negocio (edición): para EMISOR/GERENTE/CAJERO, si se editan
            // establecimientos/puntos, exigir 1 punto por cada establecimiento que tenga
            // puntos disponibles (ACTIVO + LIBRE). Si un establecimiento no tiene puntos
            // disponibles, no es obligatorio escoger.
            $isRoleConAsociacion = in_array($user->role, [UserRole::EMISOR, UserRole::GERENTE, UserRole::CAJERO], true);
            $touchesAsociaciones = $request->has('establecimientos_ids') || $request->has('puntos_emision_ids');
            if ($isRoleConAsociacion && $touchesAsociaciones) {
                $establecimientosIds = $request->has('establecimientos_ids')
                    ? $request->input('establecimientos_ids', [])
                    : (json_decode($user->establecimientos_ids ?? '[]', true) ?? []);
                $establecimientosIds = is_array($establecimientosIds) ? $establecimientosIds : [];
                $establecimientosIds = array_values(array_unique(array_map('intval', $establecimientosIds)));

                // Para EMISOR: si queda vacío, asociar a todos los ABIERTO del emisor
                if ($user->role === UserRole::EMISOR && empty($establecimientosIds)) {
                    $establecimientosIds = Establecimiento::query()
                        ->where('company_id', (int) $id)
                        ->where('estado', 'ABIERTO')
                        ->pluck('id')
                        ->map(fn ($v) => (int) $v)
                        ->values()
                        ->all();
                }

                if (empty($establecimientosIds)) {
                    throw ValidationException::withMessages([
                        'establecimientos_ids' => ['Debe seleccionar al menos un establecimiento.'],
                    ]);
                }

                $validEstIds = Establecimiento::query()
                    ->where('company_id', (int) $id)
                    ->where('estado', 'ABIERTO')
                    ->whereIn('id', $establecimientosIds)
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->values()
                    ->all();

                if (count($validEstIds) !== count($establecimientosIds)) {
                    throw ValidationException::withMessages([
                        'establecimientos_ids' => ['Hay establecimientos inválidos o no disponibles (deben estar ABIERTO).'],
                    ]);
                }

                // Solo exigir punto si el establecimiento tiene puntos disponibles (ACTIVO + LIBRE)
                $requiredEstIds = PuntoEmision::query()
                    ->where('company_id', (int) $id)
                    ->whereIn('establecimiento_id', $establecimientosIds)
                    ->where('estado', 'ACTIVO')
                    ->where('estado_disponibilidad', PuntoEmisionDisponibilidadService::LIBRE)
                    ->select('establecimiento_id')
                    ->distinct()
                    ->pluck('establecimiento_id')
                    ->map(fn ($v) => (int) $v)
                    ->values()
                    ->all();

                $currentAssigned = $user->puntos_emision_ids ?? [];
                if (is_string($currentAssigned)) {
                    $decoded = json_decode($currentAssigned, true);
                    $currentAssigned = is_array($decoded) ? $decoded : [];
                }
                $currentAssigned = array_values(array_unique(array_map('intval', is_array($currentAssigned) ? $currentAssigned : [])));

                $puntosIds = $request->has('puntos_emision_ids')
                    ? $request->input('puntos_emision_ids', [])
                    : $currentAssigned;
                $puntosIds = is_array($puntosIds) ? $puntosIds : [];
                $puntosIds = array_values(array_unique(array_map('intval', $puntosIds)));

                if (count($puntosIds) !== count($requiredEstIds)) {
                    throw ValidationException::withMessages([
                        'puntos_emision_ids' => ['Debe asignar 1 punto de emisión por cada establecimiento que tenga puntos disponibles.'],
                    ]);
                }

                // Permitir puntos LIBRE o puntos ya asignados al mismo usuario (pueden estar OCUPADO)
                $puntos = PuntoEmision::query()
                    ->where('company_id', (int) $id)
                    ->whereIn('id', $puntosIds)
                    ->where('estado', 'ACTIVO')
                    ->where(function ($q) use ($currentAssigned) {
                        $q->where('estado_disponibilidad', PuntoEmisionDisponibilidadService::LIBRE);
                        if (!empty($currentAssigned)) {
                            $q->orWhereIn('id', $currentAssigned);
                        }
                    })
                    ->get(['id', 'establecimiento_id']);

                if ($puntos->count() !== count($puntosIds)) {
                    throw ValidationException::withMessages([
                        'puntos_emision_ids' => ['Hay puntos inválidos/no disponibles (deben estar ACTIVO y LIBRE).'],
                    ]);
                }

                $byEst = [];
                foreach ($puntos as $p) {
                    $estId = (int) $p->establecimiento_id;
                    if (!in_array($estId, $establecimientosIds, true)) {
                        throw ValidationException::withMessages([
                            'puntos_emision_ids' => ['Hay puntos que no pertenecen a los establecimientos seleccionados.'],
                        ]);
                    }
                    if (isset($byEst[$estId])) {
                        throw ValidationException::withMessages([
                            'puntos_emision_ids' => ['No se permite más de un punto para el mismo establecimiento.'],
                        ]);
                    }
                    $byEst[$estId] = (int) $p->id;
                }

                foreach ($requiredEstIds as $estId) {
                    if (!isset($byEst[(int) $estId])) {
                        throw ValidationException::withMessages([
                            'puntos_emision_ids' => ['Debe asignar un punto de emisión por cada establecimiento que tenga puntos disponibles.'],
                        ]);
                    }
                }
            }

            DB::beginTransaction();

            // Registrar cambios para auditoría
            $cambios = [];

            // Actualizar campos permitidos
            $campos = ['cedula', 'nombres', 'apellidos', 'username', 'email', 'estado'];
            foreach ($campos as $campo) {
                if ($request->has($campo) && $user->{$campo} != $request->input($campo)) {
                    $cambios[$campo] = [
                        'anterior' => $user->{$campo},
                        'nuevo' => $request->input($campo)
                    ];
                    $user->{$campo} = $request->input($campo);
                }
            }

            // Actualizar password si se proporciona
            if ($request->has('password') && !empty($request->input('password'))) {
                $cambios['password'] = 'actualizada';
                $user->password = Hash::make($request->input('password'));
            }

            // Actualizar establecimientos si se proporcionan
            if ($request->has('establecimientos_ids')) {
                $nuevos = $request->input('establecimientos_ids', []);
                $antiguos = json_decode($user->establecimientos_ids ?? '[]', true);
                if ($nuevos != $antiguos) {
                    $cambios['establecimientos_ids'] = [
                        'anterior' => $antiguos,
                        'nuevo' => $nuevos
                    ];
                    $user->establecimientos_ids = json_encode($nuevos);
                }
            }

            // Actualizar puntos de emisión si se proporcionan
            if ($request->has('puntos_emision_ids')) {
                $nuevos = $request->input('puntos_emision_ids', []);

                $antiguos = $user->puntos_emision_ids ?? [];
                if (is_string($antiguos)) {
                    $decoded = json_decode($antiguos, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $antiguos = $decoded;
                        if (is_string($antiguos)) {
                            $decoded2 = json_decode($antiguos, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $antiguos = $decoded2;
                            }
                        }
                    } else {
                        $antiguos = [];
                    }
                }

                $nuevos = array_values(array_unique(array_map('intval', is_array($nuevos) ? $nuevos : [])));
                $antiguos = array_values(array_unique(array_map('intval', is_array($antiguos) ? $antiguos : [])));

                if ($nuevos != $antiguos) {
                    $cambios['puntos_emision_ids'] = [
                        'anterior' => $antiguos,
                        'nuevo' => $nuevos
                    ];

                    $added = array_values(array_diff($nuevos, $antiguos));
                    $removed = array_values(array_diff($antiguos, $nuevos));

                    // Guardar como JSON real (array) para evitar doble codificación
                    $user->puntos_emision_ids = $nuevos;

                    // Gestión interna: disponibilidad
                    $disp = new PuntoEmisionDisponibilidadService();
                    $disp->markOcupado((int) $id, $added);
                    $disp->recalculate((int) $id, $removed, (int) $user->id);
                }
            }

            $user->save();

            DB::commit();

            if (!empty($cambios)) {
                Log::info('Usuario del emisor actualizado', [
                    'usuario_id' => $usuario,
                    'emisor_id' => $id,
                    'actualizado_por_id' => $currentUser->id,
                    'cambios' => $cambios
                ]);
            }

            return response()->json([
                'message' => 'Usuario actualizado exitosamente',
                'data' => $user
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validación fallida',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error actualizando usuario del emisor', [
                'error' => $e->getMessage(),
                'emisor_id' => $id,
                'usuario_id' => $usuario,
                'usuario_actual_id' => $currentUser->id ?? null
            ]);
            return response()->json([
                'message' => 'Error al actualizar usuario'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Eliminar usuario del emisor
     * HU 2: Registro de usuarios asociados a un emisor
     * 
     * Restricciones:
     * - Administrador: Puede eliminar cualquier usuario
     * - Distribuidor: Solo usuarios de emisores que él registró
     * - Emisor: Solo gerentes y cajeros de su emisor
     * - Gerente: Solo cajeros asociados a sus establecimientos
     */
    public function destroyByEmisor(Request $request, $id, $usuario)
    {
        try {
            /** @var User|null $currentUser */
            $currentUser = Auth::user();
            
            $user = User::find($usuario);

            if (!$user || ($user->emisor_id != $id && $user->id != $id)) {
                return response()->json([
                    'message' => 'Usuario no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Validar que el usuario esté en estado "Nuevo"
            if ($user->estado !== 'nuevo') {
                return response()->json([
                    'message' => 'Solo se pueden eliminar usuarios en estado "Nuevo". Este usuario está en estado "' . $user->estado . '". Para cambiar su estado, utiliza la opción editar.'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Validar permisos según jerarquía
            $canDelete = false;
            
            // Un usuario no puede eliminarse a sí mismo
            if ($currentUser->id == $usuario) {
                return response()->json([
                    'message' => 'No puedes eliminarte a ti mismo'
                ], Response::HTTP_FORBIDDEN);
            }
            
            // Administrador puede eliminar cualquier usuario
            if ($currentUser->role === UserRole::ADMINISTRADOR) {
                $canDelete = true;
            }
            // Distribuidor: verificar que el emisor fue creado por él
            elseif ($currentUser->role === UserRole::DISTRIBUIDOR) {
                $emisor = \App\Models\Company::find($id);
                $canDelete = $emisor && $emisor->created_by == $currentUser->id;
            }
            // Emisor puede eliminar gerentes y cajeros de su emisor
            elseif ($currentUser->role === UserRole::EMISOR && $currentUser->emisor_id == $id) {
                $canDelete = in_array($user->role->value, ['gerente', 'cajero']);
            }
            // Gerente puede eliminar cajeros asociados a sus establecimientos
            elseif ($currentUser->role === UserRole::GERENTE && $currentUser->emisor_id == $id) {
                if ($user->role === UserRole::CAJERO) {
                    // Verificar establecimientos en común
                    $gerenteEsts = json_decode($currentUser->establecimientos_ids ?? '[]', true);
                    $cajeroEsts = json_decode($user->establecimientos_ids ?? '[]', true);
                    $canDelete = !empty(array_intersect($gerenteEsts, $cajeroEsts));
                }
            }
            
            if (!$canDelete) {
                return response()->json([
                    'message' => 'No tienes permisos para eliminar este usuario'
                ], Response::HTTP_FORBIDDEN);
            }

            // Validar contraseña del usuario actual
            $password = $request->input('password');
            if (!Hash::check($password, $currentUser->password)) {
                return response()->json([
                    'message' => 'Contraseña incorrecta'
                ], Response::HTTP_UNAUTHORIZED);
            }

            DB::beginTransaction();

            Log::info('Usuario del emisor eliminado', [
                'usuario_eliminado_id' => $usuario,
                'cedula' => $user->cedula,
                'username' => $user->username,
                'emisor_id' => $id,
                'eliminado_por_id' => $currentUser->id,
                'rol_eliminador' => $currentUser->role->value,
                'estado' => $user->estado,
                'timestamp' => now()
            ]);

            $user->delete();

            // Recalcular disponibilidad de puntos asociados (si existían)
            try {
                $companyId = (int) $id;
                $puntos = $user->puntos_emision_ids;
                if (is_string($puntos)) {
                    $puntos = json_decode($puntos, true) ?? [];
                }
                if ($companyId > 0 && is_array($puntos) && !empty($puntos)) {
                    (new PuntoEmisionDisponibilidadService())->recalculate($companyId, $puntos, (int) $user->id);
                }
            } catch (\Exception $e) {
                Log::warning('No se pudo recalcular disponibilidad de puntos al eliminar usuario del emisor', [
                    'usuario_id' => $usuario,
                    'emisor_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Usuario eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error eliminando usuario del emisor', [
                'error' => $e->getMessage(),
                'emisor_id' => $id,
                'usuario_id' => $usuario,
                'usuario_actual_id' => $currentUser->id ?? null
            ]);
            return response()->json([
                'message' => 'Error al eliminar usuario'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Verificar email del usuario con token
     */
    public function verifyEmail(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required|string'
            ]);

            // Primero buscar el token sin filtrar por 'used' para poder dar mensajes específicos
            $tokenRecord = DB::table('user_verification_tokens')
                ->where('token', $request->token)
                ->where('type', 'email_verification')
                ->first();

            // Si el token no existe
            if (!$tokenRecord) {
                return response()->json([
                    'message' => 'Token inválido o no encontrado',
                    'code' => 'TOKEN_NOT_FOUND'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Si el token ya fue usado
            if ($tokenRecord->used) {
                return response()->json([
                    'message' => 'Este enlace de verificación ya fue utilizado anteriormente',
                    'code' => 'TOKEN_ALREADY_USED'
                ], Response::HTTP_CONFLICT); // 409 Conflict
            }

            // Si el token expiró
            if ($tokenRecord->expires_at <= now()) {
                return response()->json([
                    'message' => 'El enlace de verificación ha expirado',
                    'code' => 'TOKEN_EXPIRED'
                ], Response::HTTP_BAD_REQUEST);
            }

            $token = $tokenRecord;

            $user = User::find($token->user_id);
            
            if (!$user) {
                return response()->json([
                    'message' => 'Usuario no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }

            DB::beginTransaction();

            // Marcar token como usado
            DB::table('user_verification_tokens')
                ->where('id', $token->id)
                ->update([
                    'used' => true,
                    'used_at' => now()
                ]);

            // Actualizar estado del usuario a activo
            $user->estado = 'activo';
            $user->email_verified_at = now();
            $user->save();

            DB::commit();

            // Obtener metadata del token para saber el estado anterior
            $metadata = json_decode($token->metadata ?? '{}', true);
            $estadoAnterior = $metadata['estado_anterior'] ?? 'nuevo';

            Log::info('Email verificado', [
                'usuario_id' => $user->id,
                'email' => $user->email,
                'estado_anterior' => $estadoAnterior
            ]);

            // Solo enviar correo de cambio de contraseña si el usuario era NUEVO
            // Los usuarios suspendidos/retirados ya tienen contraseña
            if ($estadoAnterior === 'nuevo') {
                try {
                    $this->sendPasswordChangeEmail($user);
                    Log::info('Correo de cambio de contraseña enviado', ['usuario_id' => $user->id]);
                } catch (\Exception $emailError) {
                    Log::error('Error enviando correo de cambio de contraseña', [
                        'usuario_id' => $user->id,
                        'error' => $emailError->getMessage()
                    ]);
                }

                return response()->json([
                    'message' => 'Email verificado exitosamente. Revisa tu correo para establecer tu contraseña.',
                    'data' => [
                        'email' => $user->email,
                        'estado' => $user->estado
                    ]
                ]);
            } else {
                // Usuario reactivado (era suspendido/retirado)
                return response()->json([
                    'message' => 'Cuenta reactivada exitosamente. Ya puedes iniciar sesión con tu contraseña anterior.',
                    'data' => [
                        'email' => $user->email,
                        'estado' => $user->estado
                    ]
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error verificando email', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Error al verificar email'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Cambiar contraseña inicial con token
     */
    public function changeInitialPassword(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required|string',
                'password' => 'required|string|min:8|confirmed'
            ]);

            $token = DB::table('user_verification_tokens')
                ->where('token', $request->token)
                ->where('type', 'password_change')
                ->where('used', false)
                ->where('expires_at', '>', now())
                ->first();

            if (!$token) {
                return response()->json([
                    'message' => 'Token inválido o expirado'
                ], Response::HTTP_BAD_REQUEST);
            }

            $user = User::find($token->user_id);
            
            if (!$user) {
                return response()->json([
                    'message' => 'Usuario no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }

            DB::beginTransaction();

            // Marcar token como usado
            DB::table('user_verification_tokens')
                ->where('id', $token->id)
                ->update([
                    'used' => true,
                    'used_at' => now()
                ]);

            // Actualizar contraseña
            $user->password = Hash::make($request->password);
            $user->save();

            DB::commit();

            Log::info('Contraseña inicial establecida', [
                'usuario_id' => $user->id,
                'email' => $user->email
            ]);

            return response()->json([
                'message' => 'Contraseña establecida exitosamente. Ya puedes iniciar sesión.',
                'data' => [
                    'email' => $user->email,
                    'username' => $user->username
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error cambiando contraseña inicial', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Error al establecer contraseña'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Helper: Enviar email de verificación
     */
    private function sendVerificationEmail(User $user, ?string $estadoAnterior = null)
    {
        // Crear token de verificación
        $token = Str::random(60);
        
        // Si no se proporciona estado anterior, usar el estado actual del usuario
        $metadata = [
            'estado_anterior' => $estadoAnterior ?? $user->estado
        ];
        
        DB::table('user_verification_tokens')->insert([
            'user_id' => $user->id,
            'token' => $token,
            'type' => 'email_verification',
            'metadata' => json_encode($metadata),
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // URL del frontend para verificación
        $url = config('app.frontend_url', 'http://localhost:3000') . '/verify-email?token=' . $token;

        Mail::to($user->email)->send(new EmailVerificationMail($url, $user));
    }

    /**
     * Helper: Enviar email de cambio de contraseña
     */
    private function sendPasswordChangeEmail(User $user)
    {
        // Crear token para cambio de contraseña
        $token = Str::random(60);
        
        DB::table('user_verification_tokens')->insert([
            'user_id' => $user->id,
            'token' => $token,
            'type' => 'password_change',
            'expires_at' => now()->addHours(48),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // URL del frontend para cambio de contraseña
        $url = config('app.frontend_url', 'http://localhost:3000') . '/change-password?token=' . $token;

        Mail::to($user->email)->send(new PasswordChangeMail($url, $user));
    }

    /**
     * Helper: Generar contraseña temporal segura
     * Genera una contraseña aleatoria que cumple con todos los requisitos
     */
    private function generateTemporaryPassword(): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '@$!%*?&';
        
        // Asegurar al menos un carácter de cada tipo
        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];
        
        // Completar hasta 12 caracteres con caracteres aleatorios
        $allChars = $uppercase . $lowercase . $numbers . $special;
        for ($i = 0; $i < 8; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }
        
        // Mezclar los caracteres
        return str_shuffle($password);
    }

    /**
     * Verificar si un nombre de usuario ya existe
     */
    public function checkUsername(Request $request)
    {
        $username = trim((string) $request->query('username', $request->input('username', '')));
        $excludeId = $request->query('exclude_id');

        if ($username === '' || mb_strlen($username) < 3) {
            return response()->json([
                'exists' => false,
                'available' => false,
                'message' => 'Username inválido'
            ], Response::HTTP_BAD_REQUEST);
        }

        $query = User::query()->whereRaw('LOWER(username) = ?', [mb_strtolower($username)]);

        if (!empty($excludeId) && is_numeric($excludeId)) {
            $query->where('id', '!=', (int) $excludeId);
        }

        $exists = $query->exists();

        return response()->json([
            'exists' => $exists,
            'available' => !$exists,
        ], Response::HTTP_OK);
    }

    /**
     * Verificar si una cédula ya existe
     */
    public function checkCedula(Request $request)
    {
        $cedula = trim((string) $request->query('cedula', $request->input('cedula', '')));
        $excludeId = $request->query('exclude_id');

        if ($cedula === '' || strlen($cedula) !== 10) {
            return response()->json([
                'exists' => false,
                'available' => false,
                'message' => 'Cédula inválida'
            ], Response::HTTP_BAD_REQUEST);
        }

        $query = User::query()->where('cedula', $cedula);

        if (!empty($excludeId) && is_numeric($excludeId)) {
            $query->where('id', '!=', (int) $excludeId);
        }

        $exists = $query->exists();

        return response()->json([
            'exists' => $exists,
            'available' => !$exists,
        ], Response::HTTP_OK);
    }

    /**
     * Verificar si un email ya existe
     */
    public function checkEmail(Request $request)
    {
        $email = trim((string) $request->query('email', $request->input('email', '')));
        $excludeId = $request->query('exclude_id');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'exists' => false,
                'available' => false,
                'message' => 'Email inválido'
            ], Response::HTTP_BAD_REQUEST);
        }

        $query = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)]);

        if (!empty($excludeId) && is_numeric($excludeId)) {
            $query->where('id', '!=', (int) $excludeId);
        }

        $exists = $query->exists();

        return response()->json([
            'exists' => $exists,
            'available' => !$exists,
        ], Response::HTTP_OK);
    }

    /**
     * Reenviar correo de verificación y actualizar estado si es necesario
     * - nuevo: reenvía verificación, mantiene estado 'nuevo'
     * - suspendido/retirado: envía correo de reactivación, cambia a 'pendiente_verificacion'
     */
    public function resendVerificationEmail(Request $request, string $id)
    {
        try {
            /** @var User|null $currentUser */
            $currentUser = Auth::user();
            $user = User::find($id);

            if (!$user) {
                return response()->json(['message' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
            }

            // Validar que puede gestionar este usuario
            if (!PermissionService::puedeGestionarUsuario($currentUser, $user)) {
                return response()->json(['message' => 'No tienes permiso para gestionar este usuario'], Response::HTTP_FORBIDDEN);
            }

            // Verificar que el estado es válido para reenvío
            if (!in_array($user->estado, ['nuevo', 'suspendido', 'retirado'])) {
                return response()->json([
                    'message' => 'El correo de verificación solo puede reenviarse a usuarios con estado: nuevo, suspendido o retirado'
                ], Response::HTTP_BAD_REQUEST);
            }

            $estadoAnterior = $user->estado;
            $nuevoEstado = $request->input('estado', $user->estado);

            // Lógica de cambio de estado
            if (in_array($estadoAnterior, ['suspendido', 'retirado'])) {
                // Cambiar a pendiente_verificacion para reactivación
                $user->estado = 'pendiente_verificacion';
                $user->save();

                Log::info('Usuario cambiado a pendiente_verificacion para reactivación', [
                    'usuario_id' => $user->id,
                    'estado_anterior' => $estadoAnterior,
                    'estado_nuevo' => 'pendiente_verificacion',
                    'gestionado_por' => $currentUser->id
                ]);
            }

            // Enviar correo de verificación con el estado anterior
            $this->sendVerificationEmail($user, $estadoAnterior);

            $mensaje = match($estadoAnterior) {
                'nuevo' => 'Correo de verificación reenviado exitosamente',
                'suspendido' => 'Correo de reactivación enviado. Estado cambiado a Pendiente Verificación',
                'retirado' => 'Correo de reactivación enviado. Estado cambiado a Pendiente Verificación',
                default => 'Correo enviado exitosamente'
            };

            Log::info('Correo de verificación reenviado', [
                'usuario_id' => $user->id,
                'email' => $user->email,
                'estado_anterior' => $estadoAnterior,
                'estado_actual' => $user->estado,
                'reenviado_por' => $currentUser->id
            ]);

            return response()->json([
                'message' => $mensaje,
                'estado_anterior' => $estadoAnterior,
                'estado_actual' => $user->estado
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error reenviando correo de verificación', [
                'error' => $e->getMessage(),
                'usuario_id' => $id ?? null,
                'gestionado_por' => $currentUser->id ?? null
            ]);

            return response()->json([
                'message' => 'Error al reenviar el correo de verificación'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reenviar correo de verificación para usuarios de emisor
     */
    public function resendVerificationEmailByEmisor(Request $request, string $emiId, string $userId)
    {
        try {
            /** @var User|null $currentUser */
            $currentUser = Auth::user();
            $user = User::find($userId);

            if (!$user) {
                return response()->json(['message' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
            }

            // Validar que puede gestionar este usuario
            if (!PermissionService::puedeGestionarUsuario($currentUser, $user)) {
                return response()->json(['message' => 'No tienes permiso para gestionar este usuario'], Response::HTTP_FORBIDDEN);
            }

            // Verificar que el usuario pertenece al emisor especificado
            if ($user->emisor_id != $emiId && $user->id != $emiId) {
                return response()->json(['message' => 'El usuario no pertenece a este emisor'], Response::HTTP_FORBIDDEN);
            }

            // Verificar que el estado es válido para reenvío
            if (!in_array($user->estado, ['nuevo', 'suspendido', 'retirado'])) {
                return response()->json([
                    'message' => 'El correo de verificación solo puede reenviarse a usuarios con estado: nuevo, suspendido o retirado'
                ], Response::HTTP_BAD_REQUEST);
            }

            $estadoAnterior = $user->estado;

            // Cambiar estado si es necesario
            if (in_array($estadoAnterior, ['suspendido', 'retirado'])) {
                $user->estado = 'pendiente_verificacion';
                $user->save();
            }

            // Enviar correo con el estado anterior
            $this->sendVerificationEmail($user, $estadoAnterior);

            $mensaje = match($estadoAnterior) {
                'nuevo' => 'Correo de verificación reenviado exitosamente',
                'suspendido' => 'Correo de reactivación enviado. Estado cambiado a Pendiente Verificación',
                'retirado' => 'Correo de reactivación enviado. Estado cambiado a Pendiente Verificación',
                default => 'Correo enviado exitosamente'
            };

            Log::info('Correo de verificación reenviado (por emisor)', [
                'usuario_id' => $user->id,
                'emisor_id' => $emiId,
                'estado_anterior' => $estadoAnterior,
                'estado_actual' => $user->estado,
                'reenviado_por' => $currentUser->id
            ]);

            return response()->json([
                'message' => $mensaje,
                'estado_anterior' => $estadoAnterior,
                'estado_actual' => $user->estado
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error reenviando correo de verificación (emisor)', [
                'error' => $e->getMessage(),
                'emisor_id' => $emiId ?? null,
                'usuario_id' => $userId ?? null
            ]);

            return response()->json([
                'message' => 'Error al reenviar el correo de verificación'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
