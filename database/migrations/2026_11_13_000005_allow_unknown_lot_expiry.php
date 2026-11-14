<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes', fn (Blueprint $table) => $table->date('fecha_vencimiento')->nullable()->change());
    }

    public function down(): void
    {
        if (DB::table('lotes')->whereNull('fecha_vencimiento')->exists()) {
            throw new RuntimeException('No se puede exigir vencimiento: existen lotes sin fecha conocida. Complete datos reales antes de revertir.');
        }
        Schema::table('lotes', fn (Blueprint $table) => $table->date('fecha_vencimiento')->nullable(false)->change());
    }
};
