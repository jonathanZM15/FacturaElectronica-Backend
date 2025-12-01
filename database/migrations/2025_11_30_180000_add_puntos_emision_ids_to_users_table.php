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
        Schema::table('users', function (Blueprint $table) {
            // Agregar columna para almacenar IDs de puntos de emisión como JSON
            if (!Schema::hasColumn('users', 'puntos_emision_ids')) {
                $table->json('puntos_emision_ids')->nullable()->after('establecimientos_ids');
            }
            
            // Agregar columna para almacenar nombre completo si no existe
            if (!Schema::hasColumn('users', 'nombre_completo')) {
                $table->string('nombre_completo')->nullable()->after('apellidos');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'puntos_emision_ids')) {
                $table->dropColumn('puntos_emision_ids');
            }
            
            if (Schema::hasColumn('users', 'nombre_completo')) {
                $table->dropColumn('nombre_completo');
            }
        });
    }
};
