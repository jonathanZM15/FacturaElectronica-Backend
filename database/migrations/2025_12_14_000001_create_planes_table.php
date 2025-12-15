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
        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            
            // Nombre del plan
            $table->string('nombre')->comment('Nombre del plan de facturación');
            
            // Cantidad de comprobantes
            $table->unsignedInteger('cantidad_comprobantes')->comment('Cantidad de comprobantes incluidos en el plan');
            
            // Precio
            $table->decimal('precio', 10, 2)->comment('Precio del plan');
            
            // Período
            $table->enum('periodo', ['Mensual', 'Trimestral', 'Semestral', 'Anual', 'Bianual', 'Trianual'])
                  ->default('Mensual')
                  ->comment('Período de facturación del plan');
            
            // Observación (opcional)
            $table->text('observacion')->nullable()->comment('Observaciones adicionales del plan');
            
            // Color de fondo (hexadecimal)
            $table->string('color_fondo', 7)->default('#808080')->comment('Color de fondo en formato hexadecimal');
            
            // Color de texto (hexadecimal)
            $table->string('color_texto', 7)->default('#000000')->comment('Color de texto en formato hexadecimal');
            
            // Estado
            $table->enum('estado', ['Activo', 'Desactivado'])->default('Activo')->comment('Estado del plan');
            
            // Comprobantes mínimos
            $table->unsignedInteger('comprobantes_minimos')->default(5)->comment('Cantidad mínima de comprobantes');
            
            // Días mínimos
            $table->unsignedInteger('dias_minimos')->default(5)->comment('Días mínimos del plan');
            
            // Auditoría
            $table->unsignedBigInteger('created_by_id')->nullable()->comment('Usuario que creó el plan');
            $table->unsignedBigInteger('updated_by_id')->nullable()->comment('Usuario que actualizó el plan');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('estado');
            $table->index('periodo');
            $table->index('created_by_id');
            
            // Foreign keys
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planes');
    }
};
