<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', fn (Blueprint $table) => $table->string('identificacion', 20)->change());
    }

    public function down(): void
    {
        if (DB::table('clientes')->whereRaw('LENGTH(identificacion) > 13')->exists()) {
            throw new RuntimeException('No se puede reducir identificacion a 13 sin revisar los documentos de mayor longitud.');
        }
        Schema::table('clientes', fn (Blueprint $table) => $table->string('identificacion', 13)->change());
    }
};
