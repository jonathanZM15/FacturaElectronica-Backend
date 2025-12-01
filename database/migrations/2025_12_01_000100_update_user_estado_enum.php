<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Postgres compatible: usar VARCHAR y default 'nuevo'
        DB::statement("ALTER TABLE users ALTER COLUMN estado TYPE VARCHAR(40)");
        DB::statement("ALTER TABLE users ALTER COLUMN estado SET DEFAULT 'nuevo'");

        // Asegurar que el admin tenga 'activo'
        DB::statement("UPDATE users SET estado = 'activo' WHERE email = 'admin@factura.local'");
        // Para el resto, si estaban en valores viejos, mapear: 'inactivo' -> 'retirado'
        DB::statement("UPDATE users SET estado = 'retirado' WHERE estado = 'inactivo' AND email <> 'admin@factura.local'");
        // Si tenían 'activo' o 'suspendido' se mantiene; los nulls pasar a 'nuevo'
        DB::statement("UPDATE users SET estado = 'nuevo' WHERE (estado IS NULL OR estado = '') AND email <> 'admin@factura.local'");
    }

    public function down(): void
    {
        // Revertir: mantener VARCHAR y default 'activo'
        DB::statement("ALTER TABLE users ALTER COLUMN estado SET DEFAULT 'activo'");
        DB::statement("UPDATE users SET estado = 'inactivo' WHERE estado IN ('nuevo','retirado','pendiente_verificacion') AND email <> 'admin@factura.local'");
    }
};
