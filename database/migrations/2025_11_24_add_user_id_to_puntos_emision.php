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
            // Agregar columna user_id si no existe
            if (!Schema::hasColumn('puntos_emision', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('establecimiento_id')
                    ->constrained('users')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('puntos_emision', function (Blueprint $table) {
            if (Schema::hasColumn('puntos_emision', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
        });
    }
};
