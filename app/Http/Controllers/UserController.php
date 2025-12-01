<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Services\PermissionService;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
            $perPage = max(10, min(100, (int)($request->input('per_page', 20))));
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
            $puedeVer = $currentUser->id === $user->id || // Se ve a sí mismo
                        PermissionService::puedeGestionarUsuario($currentUser, $user); // O puede gestionarlo

            if (!$puedeVer) {
                Log::warning('Intento de acceso no autorizado a usuario', [
                    'usuario_id' => $id,
                    'solicitante_id' => $currentUser->id,
                    'rol_solicitante' => $currentUser->role->value
                ]);
                return response()->json(['message' => 'No tienes permiso para ver este usuario'], Response::HTTP_FORBIDDEN);
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
                'password' => Hash::make($validated['password']),
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

            Log::info('Usuario eliminado', [
                'usuario_eliminado_id' => $id,
                'usuario_eliminado_email' => $user->email,
                'usuario_eliminado_role' => $user->role->value,
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
     */
    public function indexByEmisor(Request $request, $id)
    {
        try {
            /** @var User|null $currentUser */
            $currentUser = Auth::user();
            
            // Validar permisos
            $canView = false;
            $limitedToSelf = false;
            
            if ($currentUser->role === UserRole::ADMINISTRADOR || $currentUser->role === UserRole::DISTRIBUIDOR) {
                // Administrador y Distribuidor pueden ver usuarios en cualquier emisor
                $canView = true;
            } elseif (($currentUser->role === UserRole::EMISOR || $currentUser->role === UserRole::GERENTE) && 
                      $currentUser->emisor_id == $id) {
                // Emisor y Gerente solo ven usuarios de su emisor
                $canView = true;
            } elseif ($currentUser->role === UserRole::CAJERO && $currentUser->emisor_id == $id) {
                // Cajero solo puede ver su propio usuario dentro de su emisor
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

            // Listar usuarios del emisor - solo usuarios con emisor_id coincidente
            $query = User::where('emisor_id', $id);

            if ($limitedToSelf) {
                $query->where('id', $currentUser->id);
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

            Log::info('Usuarios del emisor listados', [
                'usuario_actual_id' => $currentUser->id,
                'rol' => $currentUser->role->value,
                'emisor_id' => $id,
                'total' => $users->total()
            ]);

            return response()->json([
                'data' => $users->items(),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'last_page' => $users->lastPage(),
                    'from' => $users->firstItem() ?? 0,
                    'to' => $users->lastItem() ?? 0,
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

            DB::beginTransaction();

            // Crear usuario con datos del emisor
            $user = User::create([
                'cedula' => $request->input('cedula'),
                'nombres' => $request->input('nombres'),
                'apellidos' => $request->input('apellidos'),
                'username' => $request->input('username'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'role' => UserRole::from($request->input('role')),
                'estado' => 'activo',
                'created_by_id' => $currentUser->id,
                'emisor_id' => $id,
                'establecimientos_ids' => json_encode($request->input('establecimientos_ids', [])),
            ]);

            // Si proporciona puntos de emisión, almacenarlos
            if ($request->has('puntos_emision_ids')) {
                $user->update([
                    'puntos_emision_ids' => json_encode($request->input('puntos_emision_ids', []))
                ]);
            }

            DB::commit();

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

            return response()->json([
                'message' => 'Usuario creado exitosamente',
                'data' => $user
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
            
            // Validar permisos
            $canView = false;
            
            if ($currentUser->role === UserRole::ADMINISTRADOR || $currentUser->role === UserRole::DISTRIBUIDOR) {
                // Administrador y Distribuidor pueden ver cualquier usuario
                $canView = true;
            } elseif (($currentUser->role === UserRole::EMISOR || $currentUser->role === UserRole::GERENTE) && 
                      $currentUser->emisor_id == $id) {
                // Emisor y Gerente solo ven usuarios de su emisor
                $canView = true;
            } elseif ($currentUser->id == $usuario) {
                // Un usuario puede verse a sí mismo
                $canView = true;
            }
            
            if (!$canView) {
                return response()->json([
                    'message' => 'No tienes permisos para ver este usuario'
                ], Response::HTTP_FORBIDDEN);
            }

            $user = User::find($usuario);

            if (!$user || ($user->emisor_id != $id && $user->id != $id)) {
                return response()->json([
                    'message' => 'Usuario no encontrado'
                ], Response::HTTP_NOT_FOUND);
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
     */
    public function updateByEmisor(UpdateUserRequest $request, $id, $usuario)
    {
        try {
            /** @var User|null $currentUser */
            $currentUser = Auth::user();
            
            // Validar permisos
            $canUpdate = false;
            
            if ($currentUser->role === UserRole::ADMINISTRADOR || $currentUser->role === UserRole::DISTRIBUIDOR) {
                // Administrador y Distribuidor pueden actualizar cualquier usuario
                $canUpdate = true;
            } elseif (($currentUser->role === UserRole::EMISOR || $currentUser->role === UserRole::GERENTE) && 
                      $currentUser->emisor_id == $id) {
                // Emisor y Gerente solo actualizan usuarios de su emisor
                $canUpdate = true;
            } elseif ($currentUser->id == $usuario) {
                // Un usuario puede actualizarse a sí mismo
                $canUpdate = true;
            }
            
            if (!$canUpdate) {
                return response()->json([
                    'message' => 'No tienes permisos para actualizar este usuario'
                ], Response::HTTP_FORBIDDEN);
            }

            $user = User::find($usuario);

            if (!$user || ($user->emisor_id != $id && $user->id != $id)) {
                return response()->json([
                    'message' => 'Usuario no encontrado'
                ], Response::HTTP_NOT_FOUND);
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
                $antiguos = json_decode($user->puntos_emision_ids ?? '[]', true);
                if ($nuevos != $antiguos) {
                    $cambios['puntos_emision_ids'] = [
                        'anterior' => $antiguos,
                        'nuevo' => $nuevos
                    ];
                    $user->puntos_emision_ids = json_encode($nuevos);
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
     */
    public function destroyByEmisor(Request $request, $id, $usuario)
    {
        try {
            /** @var User|null $currentUser */
            $currentUser = Auth::user();
            
            // Validar permisos
            $canDelete = false;
            
            if ($currentUser->role === UserRole::ADMINISTRADOR || $currentUser->role === UserRole::DISTRIBUIDOR) {
                // Administrador y Distribuidor pueden eliminar cualquier usuario
                $canDelete = true;
            } elseif (($currentUser->role === UserRole::EMISOR || $currentUser->role === UserRole::GERENTE) && 
                      $currentUser->emisor_id == $id) {
                // Emisor y Gerente solo eliminan usuarios de su emisor
                $canDelete = true;
            }
            
            if (!$canDelete) {
                return response()->json([
                    'message' => 'No tienes permisos para eliminar usuarios en este emisor'
                ], Response::HTTP_FORBIDDEN);
            }

            $user = User::find($usuario);

            if (!$user || ($user->emisor_id != $id && $user->id != $id)) {
                return response()->json([
                    'message' => 'Usuario no encontrado'
                ], Response::HTTP_NOT_FOUND);
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
                'timestamp' => now()
            ]);

            $user->delete();

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
}
