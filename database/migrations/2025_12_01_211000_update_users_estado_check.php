<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->isPostgres()) {
            try {
                DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_estado_check");
                DB::statement("ALTER TABLE users ALTER COLUMN estado TYPE VARCHAR(40)");
                DB::statement("ALTER TABLE users ALTER COLUMN estado SET DEFAULT 'nuevo'");
                DB::statement("ALTER TABLE users ADD CONSTRAINT users_estado_check CHECK (estado IN ('nuevo','activo','pendiente_verificacion','suspendido','retirado'))");
            } catch (\Exception $e) {
                // Ignore if already modified
            }
        }

        try {
            DB::statement("UPDATE users SET estado = LOWER(estado) WHERE estado IS NOT NULL");
            DB::statement("UPDATE users SET estado = 'activo' WHERE email = 'admin@factura.local'");
            DB::statement("UPDATE users SET estado = 'retirado' WHERE estado = 'inactivo' AND email <> 'admin@factura.local'");
            DB::statement("UPDATE users SET estado = 'nuevo' WHERE (estado IS NULL OR estado = '') AND email <> 'admin@factura.local'");
        } catch (\Exception $e) {
            // Ignore if table doesn't exist
        }
    }

    public function down(): void
    {
        if ($this->isPostgres()) {
            try {
                DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_estado_check");
                DB::statement("ALTER TABLE users ALTER COLUMN estado SET DEFAULT 'activo'");
                DB::statement("ALTER TABLE users ADD CONSTRAINT users_estado_check CHECK (estado IN ('activo','inactivo','suspendido'))");
            } catch (\Exception $e) {
                // Ignore
            }
        }
        
        try {
            DB::statement("UPDATE users SET estado = 'inactivo' WHERE estado IN ('nuevo','retirado','pendiente_verificacion') AND email <> 'admin@factura.local'");
        } catch (\Exception $e) {
            // Ignore
        }
    }

    private function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
