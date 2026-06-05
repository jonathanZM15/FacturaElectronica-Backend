<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tipos_impuesto')) {
            return;
        }

        if (Schema::hasColumn('tipos_impuesto', 'codigo')) {
            DB::statement('ALTER TABLE tipos_impuesto DROP CONSTRAINT IF EXISTS tipos_impuesto_codigo_unique');
            DB::statement('ALTER TABLE tipos_impuesto RENAME COLUMN codigo TO codigo_porcentaje');
        }

        if (!Schema::hasColumn('tipos_impuesto', 'codigo_impuesto')) {
            Schema::table('tipos_impuesto', function (Blueprint $table) {
                $table->unsignedInteger('codigo_impuesto')->default(2);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('tipos_impuesto')) {
            return;
        }

        if (Schema::hasColumn('tipos_impuesto', 'codigo_impuesto')) {
            Schema::table('tipos_impuesto', function (Blueprint $table) {
                $table->dropColumn('codigo_impuesto');
            });
        }

        if (Schema::hasColumn('tipos_impuesto', 'codigo_porcentaje')) {
            DB::statement('ALTER TABLE tipos_impuesto RENAME COLUMN codigo_porcentaje TO codigo');
            Schema::table('tipos_impuesto', function (Blueprint $table) {
                $table->unique('codigo');
            });
        }
    }
};
