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
        Schema::create('puntos_emision', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->onDelete('cascade');
            $table->string('codigo', 3)->unique();
            $table->enum('estado', ['ACTIVO', 'DESACTIVADO'])->default('ACTIVO');
            $table->string('nombre', 255);
            $table->unsignedInteger('secuencial_factura')->default(1);
            $table->unsignedInteger('secuencial_liquidacion_compra')->default(1);
            $table->unsignedInteger('secuencial_nota_credito')->default(1);
            $table->unsignedInteger('secuencial_nota_debito')->default(1);
            $table->unsignedInteger('secuencial_guia_remision')->default(1);
            $table->unsignedInteger('secuencial_retencion')->default(1);
            $table->unsignedInteger('secuencial_proforma')->default(1);
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('company_id');
            $table->index('establecimiento_id');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('puntos_emision');
    }
};
