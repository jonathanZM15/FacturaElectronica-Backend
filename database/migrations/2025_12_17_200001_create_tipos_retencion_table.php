<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Módulo 7: Gestión de Tipos de Retención
     */
    public function up(): void
    {
        Schema::create('tipos_retencion', function (Blueprint $table) {
            $table->id();
            
            // Tipo de retención: IVA, RENTA, ISD
            $table->enum('tipo_retencion', ['IVA', 'RENTA', 'ISD']);
            
            // Código: letras y números, sin espacios ni caracteres especiales
            $table->string('codigo', 50);
            
            // Nombre: cualquier carácter
            $table->string('nombre', 255);
            
            // Porcentaje: valor numérico con hasta dos decimales
            $table->decimal('porcentaje', 5, 2);
            
            // Auditoría
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('created_by_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            
            $table->foreign('updated_by_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            
            // Índices para búsquedas
            $table->index('tipo_retencion');
            $table->index('codigo');
            $table->index('nombre');
            $table->index('created_at');
            $table->index('updated_at');
            
            // Índice único compuesto: tipo_retencion + codigo
            $table->unique(['tipo_retencion', 'codigo'], 'unique_tipo_codigo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_retencion');
    }
};
