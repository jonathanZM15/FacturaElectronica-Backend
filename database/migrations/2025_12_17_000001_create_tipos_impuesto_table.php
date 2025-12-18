<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Módulo 7: Gestión de Tipos de Impuesto
     * Tabla para almacenar los tipos de impuesto disponibles (IVA, ICE, IRBPNR)
     */
    public function up(): void
    {
        Schema::create('tipos_impuesto', function (Blueprint $table) {
            $table->id();
            
            // Tipo de impuesto: IVA, ICE, IRBPNR
            $table->string('tipo_impuesto', 20);
            
            // Tipo de tarifa: Porcentaje, Importe fijo por unidad
            $table->string('tipo_tarifa', 30);
            
            // Código único del tipo de impuesto (entero positivo)
            $table->unsignedInteger('codigo')->unique();
            
            // Nombre único del tipo de impuesto
            $table->string('nombre', 100);
            
            // Valor de la tarifa (puede ser porcentaje o importe fijo)
            $table->decimal('valor_tarifa', 10, 2);
            
            // Estado: Activo/Desactivado
            $table->string('estado', 15)->default('Activo');
            
            // Usuario que creó el registro
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Usuario que actualizó el registro
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            
            // Índices para búsquedas frecuentes
            $table->index('tipo_impuesto');
            $table->index('estado');
            $table->index('nombre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_impuesto');
    }
};
