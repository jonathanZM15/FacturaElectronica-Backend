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
        return match ($role) {
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
     * Un distribuidor puede gestionar a emisores, gerentes y cajeros creados por él
     * Un emisor puede gestionar a gerentes y cajeros creados por él
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

        // Distribuidor puede gestionar a sus usuarios creados
        if ($roleGestor === UserRole::DISTRIBUIDOR) {
            return $objetivo->created_by_id === $gestor->id &&
                   in_array($roleObjetivo->value, ['emisor', 'gerente', 'cajero']);
        }

        // Emisor puede gestionar gerentes y cajeros creados por él
        if ($roleGestor === UserRole::EMISOR) {
            return $objetivo->created_by_id === $gestor->id &&
                   in_array($roleObjetivo->value, ['gerente', 'cajero']);
        }

        // Gerente puede gestionar cajeros en sus establecimientos
        if ($roleGestor === UserRole::GERENTE) {
            return $objetivo->created_by_id === $gestor->id &&
                   $roleObjetivo === UserRole::CAJERO;
        }

        return false;
    }
}
