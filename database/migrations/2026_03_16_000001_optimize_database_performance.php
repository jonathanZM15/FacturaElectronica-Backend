<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Optimizar performance de base de datos agregando índices
     * faltantes en columnas frecuentemente usadas en filtros,
     * búsquedas y JOINs.
     * 
     * Compatible con: MySQL y PostgreSQL
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // ============ TABLA: users ============
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) use ($driver) {
                // Índices para búsqueda de texto
                $this->createIndexIfNotExists('users', 'cedula', 'idx_users_cedula', $driver);
                $this->createIndexIfNotExists('users', 'nombres', 'idx_users_nombres', $driver);
                $this->createIndexIfNotExists('users', 'apellidos', 'idx_users_apellidos', $driver);
                $this->createIndexIfNotExists('users', 'username', 'idx_users_username', $driver);

                // Índices para JOINs y relaciones
                $this->createIndexIfNotExists('users', 'distribuidor_id', 'idx_users_distribuidor_id', $driver);
                $this->createIndexIfNotExists('users', 'emisor_id', 'idx_users_emisor_id', $driver);

                // Índices compuestos
                $this->createCompositeIndexIfNotExists('users', ['role', 'estado'], 'idx_users_role_estado', $driver);
                $this->createCompositeIndexIfNotExists('users', ['created_by_id', 'created_at'], 'idx_users_created_by_created_at', $driver);
                $this->createCompositeIndexIfNotExists('users', ['estado', 'created_at'], 'idx_users_estado_created_at', $driver);

                // Índice para verificaciones
                $this->createIndexIfNotExists('users', 'email_verified_at', 'idx_users_email_verified_at', $driver);
            });
        }

        // ============ TABLA: login_attempts ============
        if (Schema::hasTable('login_attempts')) {
            Schema::table('login_attempts', function (Blueprint $table) use ($driver) {
                $this->createCompositeIndexIfNotExists('login_attempts', ['user_id', 'ip_address', 'attempted_at'], 'idx_login_user_ip_date', $driver);
                $this->createCompositeIndexIfNotExists('login_attempts', ['ip_address', 'attempted_at'], 'idx_login_ip_date', $driver);
                $this->createCompositeIndexIfNotExists('login_attempts', ['success', 'attempted_at'], 'idx_login_success_date', $driver);
            });
        }

        // ============ TABLA: user_verification_tokens ============
        if (Schema::hasTable('user_verification_tokens')) {
            Schema::table('user_verification_tokens', function (Blueprint $table) use ($driver) {
                $this->createIndexIfNotExists('user_verification_tokens', 'expires_at', 'idx_token_expires_at', $driver);
                $this->createCompositeIndexIfNotExists('user_verification_tokens', ['user_id', 'type', 'used_at'], 'idx_token_user_type_used', $driver);
            });
        }

        // ============ TABLA: suscripciones ============
        if (Schema::hasTable('suscripciones')) {
            Schema::table('suscripciones', function (Blueprint $table) use ($driver) {
                $this->createCompositeIndexIfNotExists('suscripciones', ['fecha_inicio', 'fecha_fin'], 'idx_suscripcion_fecha_rango', $driver);
                $this->createCompositeIndexIfNotExists('suscripciones', ['estado_suscripcion', 'fecha_fin'], 'idx_suscripcion_estado_fecha', $driver);
                $this->createCompositeIndexIfNotExists('suscripciones', ['emisor_id', 'estado_suscripcion', 'created_at'], 'idx_suscripcion_emisor_estado_date', $driver);
                $this->createCompositeIndexIfNotExists('suscripciones', ['plan_id', 'estado_suscripcion'], 'idx_suscripcion_plan_estado', $driver);
            });
        }

        // ============ TABLA: suscripcion_comision_audit ============
        if (Schema::hasTable('suscripcion_comision_audit')) {
            Schema::table('suscripcion_comision_audit', function (Blueprint $table) use ($driver) {
                $this->createCompositeIndexIfNotExists('suscripcion_comision_audit', ['suscripcion_id', 'created_at'], 'idx_suscripcion_comision_date', $driver);
            });
        }

        // ============ TABLA: suscripcion_estado_audit ============
        if (Schema::hasTable('suscripcion_estado_audit')) {
            Schema::table('suscripcion_estado_audit', function (Blueprint $table) use ($driver) {
                $this->createCompositeIndexIfNotExists('suscripcion_estado_audit', ['suscripcion_id', 'created_at'], 'idx_suscripcion_estado_date', $driver);
                $this->createCompositeIndexIfNotExists('suscripcion_estado_audit', ['tipo_transicion', 'created_at'], 'idx_suscripcion_transicion_date', $driver);
            });
        }

        // ============ TABLA: puntos_emision ============
        if (Schema::hasTable('puntos_emision')) {
            Schema::table('puntos_emision', function (Blueprint $table) use ($driver) {
                $this->createIndexIfNotExists('puntos_emision', 'user_id', 'idx_puntos_user_id', $driver);
                $this->createIndexIfNotExists('puntos_emision', 'estado_disponibilidad', 'idx_puntos_estado_disponibilidad', $driver);
                $this->createCompositeIndexIfNotExists('puntos_emision', ['company_id', 'establecimiento_id', 'estado'], 'idx_puntos_company_estab_estado', $driver);
                $this->createCompositeIndexIfNotExists('puntos_emision', ['company_id', 'user_id'], 'idx_puntos_company_user', $driver);
            });
        }

        // ============ TABLA: user_audit ============
        if (Schema::hasTable('user_audit')) {
            Schema::table('user_audit', function (Blueprint $table) use ($driver) {
                $this->createCompositeIndexIfNotExists('user_audit', ['action', 'created_at'], 'idx_user_audit_action_date', $driver);
            });
        }

        // ============ TABLA: establecimientos ============
        if (Schema::hasTable('establecimientos')) {
            Schema::table('establecimientos', function (Blueprint $table) use ($driver) {
                $this->createCompositeIndexIfNotExists('establecimientos', ['company_id', 'estado'], 'idx_estab_company_estado', $driver);
            });
        }

        // ============ TABLA: planes ============
        if (Schema::hasTable('planes')) {
            Schema::table('planes', function (Blueprint $table) use ($driver) {
                $this->createCompositeIndexIfNotExists('planes', ['estado', 'periodo'], 'idx_planes_estado_periodo', $driver);
            });
        }

        // ============ TABLA: emisores ============
        if (Schema::hasTable('emisores')) {
            Schema::table('emisores', function (Blueprint $table) use ($driver) {
                $this->createIndexIfNotExists('emisores', 'ruc', 'idx_emisor_ruc', $driver);
                $this->createIndexIfNotExists('emisores', 'estado', 'idx_emisor_estado', $driver);
                $this->createCompositeIndexIfNotExists('emisores', ['created_by', 'created_at'], 'idx_emisor_created_by_date', $driver);
            });
        }

        // ============ TABLA: tipos_impuesto ============
        if (Schema::hasTable('tipos_impuesto')) {
            Schema::table('tipos_impuesto', function (Blueprint $table) use ($driver) {
                $this->createCompositeIndexIfNotExists('tipos_impuesto', ['tipo_impuesto', 'estado'], 'idx_tipo_impuesto_tipo_estado', $driver);
            });
        }

        // ============ TABLA: tipos_retencion ============
        if (Schema::hasTable('tipos_retencion')) {
            Schema::table('tipos_retencion', function (Blueprint $table) use ($driver) {
                $this->createCompositeIndexIfNotExists('tipos_retencion', ['tipo_retencion', 'codigo'], 'idx_tipo_retencion_codigo', $driver);
            });
        }
    }

    /**
     * Revertir la migración
     */
    public function down(): void
    {
        // Los índices se eliminan automáticamente con el rollback
    }

    /**
     * Helper para crear índice simple si no existe (Compatible MySQL + PostgreSQL)
     */
    private function createIndexIfNotExists(string $table, string $column, string $indexName, string $driver): void
    {
        $indexExists = false;

        if ($driver === 'pgsql') {
            // PostgreSQL
            $result = DB::select(
                "SELECT 1 FROM pg_indexes WHERE schemaname = 'public' AND tablename = ? AND indexname = ?",
                [$table, $indexName]
            );
            $indexExists = !empty($result);
        } else {
            // MySQL
            $result = DB::select(
                "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?",
                [$table, $indexName]
            );
            $indexExists = !empty($result);
        }

        if (!$indexExists) {
            DB::statement("CREATE INDEX $indexName ON $table ($column)");
        }
    }

    /**
     * Helper para crear índice compuesto si no existe
     */
    private function createCompositeIndexIfNotExists(string $table, array $columns, string $indexName, string $driver): void
    {
        $indexExists = false;

        if ($driver === 'pgsql') {
            // PostgreSQL
            $result = DB::select(
                "SELECT 1 FROM pg_indexes WHERE schemaname = 'public' AND tablename = ? AND indexname = ?",
                [$table, $indexName]
            );
            $indexExists = !empty($result);
        } else {
            // MySQL
            $result = DB::select(
                "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?",
                [$table, $indexName]
            );
            $indexExists = !empty($result);
        }

        if (!$indexExists) {
            $columnsList = implode(', ', $columns);
            DB::statement("CREATE INDEX $indexName ON $table ($columnsList)");
        }
    }
};
