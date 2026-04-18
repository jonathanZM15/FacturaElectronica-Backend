<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Verificar primero si el índice existe para evitar errores al intentar eliminarlo varias veces
        $indexExists = collect(DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'users' AND indexname = 'users_cedula_unique'"))->isNotEmpty();
        if ($indexExists) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_cedula_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexExists = collect(DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'users' AND indexname = 'users_cedula_unique'"))->isNotEmpty();
        if (!$indexExists) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('cedula');
            });
        }
    }
};
