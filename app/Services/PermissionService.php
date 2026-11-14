<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;

class PermissionService
{
    /**
     * Define los permisos por rol
     */
    public static function obtenerPermisosPorRol(UserRole $role): array
    {
        $permissions = match ($role) {
            UserRole::ADMINISTRADOR => [
                'users.create' => true,
                'users.read' => true,
                'users.update' => true,
                'users.delete' => true,
                'users.create_any_role' => true,
                
                'emisores.create' => true,
                'emisores.read' => true,
                'emisores.update' => true,
                'emisores.delete' => true,
                
                'establecimientos.create' => true,
                'establecimientos.read' => true,
                'establecimientos.update' => true,
                'establecimientos.delete' => true,
                
                'puntos_emision.create' => true,
                'puntos_emision.read' => true,
                'puntos_emision.update' => true,
                'puntos_emision.delete' => true,
                
                'planes.create' => true,
                'planes.read' => true,
                'planes.update' => true,
                'planes.delete' => true,
                
                'impuestos.create' => true,
                'impuestos.read' => true,
                'impuestos.update' => true,
                'impuestos.delete' => true,
                
                'suscripciones.create' => true,
                'suscripciones.read' => true,
                'suscripciones.update' => true,
                'suscripciones.delete' => true,
                
                'dashboard.admin' => true,
                'dashboard.cliente' => true,
            ],

            UserRole::DISTRIBUIDOR => [
                'users.create' => true,
                'users.read' => true,
                'users.update' => true,
                'users.delete' => true,
                'users.create_any_role' => false,
                'users.create_limited_roles' => ['emisor', 'gerente', 'cajero'],
                
                'emisores.create' => true,
                'emisores.read' => true,
                'emisores.update' => true,
                'emisores.delete' => true,
                'emisores.scope' => 'own', // Solo propios
                
                'establecimientos.create' => true,
                'establecimientos.read' => true,
                'establecimientos.update' => true,
                'establecimientos.delete' => true,
                'establecimientos.scope' => 'own',
                
                'puntos_emision.create' => true,
                'puntos_emision.read' => true,
                'puntos_emision.update' => true,
                'puntos_emision.delete' => true,
                'puntos_emision.scope' => 'own',
                
                'suscripciones.create' => true,
                'suscripciones.read' => true,
                'suscripciones.update' => true,
                'suscripciones.delete' => true,
                'suscripciones.scope' => 'own',
                
                'planes.read' => true,
                'impuestos.read' => true,
                
                'dashboard.admin' => false,
                'dashboard.cliente' => false,
            ],

            UserRole::EMISOR => [
                'users.create' => true,
                'users.read' => true,
                'users.update' => true,
                'users.delete' => true,
                'users.create_limited_roles' => ['gerente', 'cajero'],
                
                'emisores.read' => true,
                'emisores.update' => true,
                'emisores.scope' => 'own',
                
                'establecimientos.create' => true,
                'establecimientos.read' => true,
                'establecimientos.update' => true,
                'establecimientos.delete' => true,
                'establecimientos.scope' => 'own',
                
                'puntos_emision.create' => true,
                'puntos_emision.read' => true,
                'puntos_emision.update' => true,
                'puntos_emision.delete' => true,
                'puntos_emision.scope' => 'own',
                
                'comprobantes.create' => true,
                'comprobantes.read' => true,
                'comprobantes.update' => true,
                
                'inventario.manage' => true,
                'clientes.manage' => true,
                'reportes.read' => true,
                
                'dashboard.admin' => false,
                'dashboard.cliente' => true,
            ],

            UserRole::GERENTE => [
                'users.create' => true,
                'users.read' => true,
                'users.update' => true,
                'users.delete' => true,
                'users.create_limited_roles' => ['cajero'],
                'users.scope' => 'establecimientos_asignados',
                
                'establecimientos.read' => true,
                'establecimientos.scope' => 'asignados',
                
                'puntos_emision.read' => true,
                'puntos_emision.scope' => 'establecimientos_asignados',
                
                'comprobantes.create' => true,
                'comprobantes.read' => true,
                'comprobantes.update' => true,
                
                'inventario.manage' => true,
                'clientes.manage' => true,
                'reportes.read' => true,
                
                'dashboard.admin' => false,
                'dashboard.cliente' => true,
            ],

            UserRole::CAJERO => [
                'comprobantes.create' => true,
                'comprobantes.read' => true,
                
                'clientes.manage' => true,
                
                'dashboard.admin' => false,
                'dashboard.cliente' => true,
            ],
        };

        $admin = $role === UserRole::ADMINISTRADOR;
        $owner = $role === UserRole::EMISOR;
        $manager = $role === UserRole::GERENTE;
        $cashier = $role === UserRole::CAJERO;
        return array_replace($permissions, [
            'emisores.read' => true,
            'planes.read' => $admin,
            'impuestos.read' => $admin,
            'catalogo.write' => $owner,
            'productos.read' => $admin || $owner || $manager || $cashier,
            'inventario.read' => $admin || $owner || $manager || $cashier,
            'kardex.read' => $admin || $owner || $manager,
            'inventario.manage' => $owner || $manager,
            'clientes.read' => $admin || $owner || $manager || $cashier,
            'clientes.write' => $owner || $cashier,
            'clientes.delete' => $owner,
            'clientes.manage' => $owner,
            'proveedores.read' => $admin || $owner || $manager,
            'proveedores.write' => $owner,
            'pos.operate' => $cashier,
            'comprobantes.create' => $cashier,
            'comprobantes.read' => $admin || $owner || $manager || $cashier,
            'comprobantes.update' => false,
            'comprobantes.retry' => $owner || $cashier,
            'establecimientos.read' => true,
            'puntos_emision.read' => true,
        ]);
    }

    /**
     * Verifica si un usuario tiene un permiso específico
     */
    public static function tienePermiso(User $user, string $permiso): bool
    {
        $permisos = self::obtenerPermisosPorRol($user->role);
        
        if (!isset($permisos[$permiso])) {
            return false;
        }

        $permiso_valor = $permisos[$permiso];
        
        // Si es boolean, retorna directamente
        if (is_bool($permiso_valor)) {
            return $permiso_valor;
        }

        return false;
    }

    /**
     * Obtiene los roles que puede crear un usuario
     */
    public static function rolesPuedoCrear(User $usuario): array
    {
        $role = $usuario->role;
        return $role->puedeCrearRoles();
    }

    /**
     * Verifica si un usuario puede crear un usuario con un rol específico
     */
    public static function puedoCrearRol(User $usuario, string $rolaCrear): bool
    {
        $roles_permitidos = self::rolesPuedoCrear($usuario);
        return in_array($rolaCrear, $roles_permitidos);
    }

    /**
     * Verifica jerarquía: ¿Puedo gestionar este usuario?
     * Un admin puede gestionar a cualquiera
     * Un distribuidor gestiona emisores, gerentes y cajeros de las empresas de su red
     * Un emisor gestiona gerentes y cajeros de su empresa sin depender del creador
     * Un gerente puede gestionar a cajeros en sus establecimientos
     * Un cajero no puede gestionar a nadie
     */
    public static function puedeGestionarUsuario(User $gestor, User $objetivo): bool
    {
        $roleGestor = $gestor->role;
        $roleObjetivo = $objetivo->role;

        // Administrador puede gestionar a todos
        if ($roleGestor === UserRole::ADMINISTRADOR) {
            return true;
        }

        // No puede auto-gestionarse
        if ($gestor->id === $objetivo->id) {
            return false;
        }

        // Distribuidor puede gestionar usuarios asociados a emisores que él registró
        if ($roleGestor === UserRole::DISTRIBUIDOR) {
            if (!in_array($roleObjetivo->value, ['emisor', 'gerente', 'cajero'], true)) {
                return false;
            }

            // Debe estar asociado a un emisor (Company) y ese emisor debe haber sido creado por el distribuidor.
            $emisorId = $objetivo->emisor_id;
            if (!$emisorId) {
                return false;
            }

            return \App\Models\Company::query()
                ->where('id', (int) $emisorId)
                ->where('created_by', (int) $gestor->id)
                ->exists();
        }

        // La pertenencia a la empresa define el alcance; el creador es auditoría.
        if ($roleGestor === UserRole::EMISOR) {
            return $gestor->emisor_id && (int) $objetivo->emisor_id === (int) $gestor->emisor_id
                && in_array($roleObjetivo, [UserRole::GERENTE, UserRole::CAJERO], true);
        }

        // Gerente puede gestionar cajeros en sus establecimientos
        if ($roleGestor === UserRole::GERENTE) {
            $allowed = BusinessAccess::establishmentIds($gestor);
            return $gestor->emisor_id && (int) $objetivo->emisor_id === (int) $gestor->emisor_id
                && $roleObjetivo === UserRole::CAJERO && !empty($objetivo->establecimientos_ids)
                && empty(array_diff($objetivo->establecimientos_ids, $allowed));
        }

        return false;
    }
}
