<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registro_operativo_detalles', function (Blueprint $table) {
            if (!Schema::hasColumn('registro_operativo_detalles', 'costo_unitario')) {
                $table->decimal('costo_unitario', 18, 6)->nullable()->default(0);
            }
            if (!Schema::hasColumn('registro_operativo_detalles', 'costo_total')) {
                $table->decimal('costo_total', 18, 6)->nullable()->default(0);
            }
            if (!Schema::hasColumn('registro_operativo_detalles', 'cantidad_actual')) {
                $table->decimal('cantidad_actual', 18, 6)->nullable();
            }
            if (!Schema::hasColumn('registro_operativo_detalles', 'cantidad_final')) {
                $table->decimal('cantidad_final', 18, 6)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('registro_operativo_detalles', function (Blueprint $table) {
            $table->dropColumn(['costo_unitario', 'costo_total', 'cantidad_actual', 'cantidad_final']);
        });
    }
};
