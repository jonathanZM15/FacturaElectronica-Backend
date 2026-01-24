<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('puntos_emision', function (Blueprint $table) {
            if (!Schema::hasColumn('puntos_emision', 'bloqueo_edicion_produccion')) {
                $table->boolean('bloqueo_edicion_produccion')->default(false)->after('secuencial_proforma');
            }
            if (!Schema::hasColumn('puntos_emision', 'bloqueo_edicion_produccion_at')) {
                $table->timestamp('bloqueo_edicion_produccion_at')->nullable()->after('bloqueo_edicion_produccion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('puntos_emision', function (Blueprint $table) {
            if (Schema::hasColumn('puntos_emision', 'bloqueo_edicion_produccion_at')) {
                $table->dropColumn('bloqueo_edicion_produccion_at');
            }
            if (Schema::hasColumn('puntos_emision', 'bloqueo_edicion_produccion')) {
                $table->dropColumn('bloqueo_edicion_produccion');
            }
        });
    }
};
