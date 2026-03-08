<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agregar índices de rendimiento a la tabla users para columnas
     * frecuentemente utilizadas en filtros, ordenamiento y JOINs.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Índices para columnas usadas en filtros de listados
            if (Schema::hasColumn('users', 'role')) {
                $table->index('role', 'idx_users_role');
            }
            if (Schema::hasColumn('users', 'estado')) {
                $table->index('estado', 'idx_users_estado');
            }
            if (Schema::hasColumn('users', 'created_by_id')) {
                $table->index('created_by_id', 'idx_users_created_by_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_role');
            $table->dropIndex('idx_users_estado');
            $table->dropIndex('idx_users_created_by_id');
        });
    }
};
