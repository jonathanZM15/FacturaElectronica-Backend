<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMINISTRADOR = 'administrador';
    case DISTRIBUIDOR = 'distribuidor';
    case EMISOR = 'emisor';
    case GERENTE = 'gerente';
    case CAJERO = 'cajero';

    /**
     * Obtiene la jerarquía del rol (mayor número = más permisos)
     */
    public function jerarquia(): int
    {
        return match ($this) {
            self::ADMINISTRADOR => 5,
            self::DISTRIBUIDOR => 4,
            self::EMISOR => 3,
            self::GERENTE => 2,
            self::CAJERO => 1,
        };
    }

    /**
     * Verifica si este rol puede crear roles menores
     */
    public function puedeCrearRoles(): array
    {
        return match ($this) {
            self::ADMINISTRADOR => ['administrador', 'distribuidor', 'emisor', 'gerente', 'cajero'],
            self::DISTRIBUIDOR => ['emisor', 'gerente', 'cajero'],
            self::EMISOR => ['gerente', 'cajero'],
            self::GERENTE => ['cajero'],
            self::CAJERO => [],
        };
    }

    /**
     * Obtiene la descripción del rol
     */
    public function descripcion(): string
    {
        return match ($this) {
            self::ADMINISTRADOR => 'Administrador del Sistema',
            self::DISTRIBUIDOR => 'Distribuidor',
            self::EMISOR => 'Emisor',
            self::GERENTE => 'Gerente',
            self::CAJERO => 'Cajero',
        };
    }

    /**
     * Verifica si es un rol administrativo
     */
    public function esAdministrativo(): bool
    {
        return in_array($this, [self::ADMINISTRADOR, self::DISTRIBUIDOR]);
    }

    /**
     * Verifica si es un rol de cliente
     */
    public function esClientPanel(): bool
    {
        return in_array($this, [self::EMISOR, self::GERENTE, self::CAJERO]);
    }
}
