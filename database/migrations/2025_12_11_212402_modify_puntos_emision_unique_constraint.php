<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('puntos_emision', function (Blueprint $table) {
            // Eliminar la constraint única en la columna codigo
            $table->dropUnique(['codigo']);
            
            // Crear una constraint única compuesta: codigo debe ser único por establecimiento y company
            $table->unique(['codigo', 'establecimiento_id', 'company_id'], 'puntos_emision_codigo_establecimiento_company_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('puntos_emision', function (Blueprint $table) {
            // Revertir: eliminar constraint compuesta
            $table->dropUnique('puntos_emision_codigo_establecimiento_company_unique');
            
            // Restaurar constraint única simple en codigo
            $table->unique('codigo');
        });
    }
};
