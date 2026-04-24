<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Only run PostgreSQL-specific ALTER statements on PostgreSQL
        if ($this->isPostgres()) {
            try {
                DB::statement("ALTER TABLE users ALTER COLUMN estado TYPE VARCHAR(40)");
                DB::statement("ALTER TABLE users ALTER COLUMN estado SET DEFAULT 'nuevo'");
            } catch (\Exception $e) {
                // Ignore if column already modified
            }
        }

        // These UPDATE statements work on all databases
        try {
            DB::statement("UPDATE users SET estado = 'activo' WHERE email = 'admin@factura.local'");
        } catch (\Exception $e) {
            // Ignore if table doesn't exist yet
        }
        
        try {
            DB::statement("UPDATE users SET estado = 'retirado' WHERE estado = 'inactivo' AND email <> 'admin@factura.local'");
            DB::statement("UPDATE users SET estado = 'nuevo' WHERE (estado IS NULL OR estado = '') AND email <> 'admin@factura.local'");
        } catch (\Exception $e) {
            // Ignore if table doesn't exist yet
        }
    }

    public function down(): void
    {
        if ($this->isPostgres()) {
            try {
                DB::statement("ALTER TABLE users ALTER COLUMN estado SET DEFAULT 'activo'");
            } catch (\Exception $e) {
                // Ignore if already reverted
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
