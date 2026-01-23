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
            if (!Schema::hasColumn('puntos_emision', 'estado_disponibilidad')) {
                $table->enum('estado_disponibilidad', ['LIBRE', 'OCUPADO'])
                    ->default('LIBRE')
                    ->after('estado');
                $table->index('estado_disponibilidad');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('puntos_emision', function (Blueprint $table) {
            if (Schema::hasColumn('puntos_emision', 'estado_disponibilidad')) {
                $table->dropIndex(['estado_disponibilidad']);
                $table->dropColumn('estado_disponibilidad');
            }
        });
    }
};
