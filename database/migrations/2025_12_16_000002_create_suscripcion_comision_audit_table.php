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
        Schema::create('suscripcion_comision_audit', function (Blueprint $table) {
            $table->id();
            
            // Relación con la suscripción
            $table->unsignedBigInteger('suscripcion_id')->comment('ID de la suscripción');
            
            // Usuario que realizó el cambio
            $table->unsignedBigInteger('user_id')->comment('Usuario que realizó el cambio');
            $table->string('user_role', 50)->comment('Rol del usuario al momento del cambio');
            
            // Campo modificado
            $table->string('campo', 100)->comment('Nombre del campo modificado');
            
            // Valores
            $table->text('valor_anterior')->nullable()->comment('Valor anterior');
            $table->text('valor_nuevo')->nullable()->comment('Valor nuevo');
            
            // Información de auditoría
            $table->string('ip_address', 45)->nullable()->comment('Dirección IP del usuario');
            $table->text('user_agent')->nullable()->comment('User agent del navegador');
            
            $table->timestamp('created_at')->useCurrent();
            
            // Índices
            $table->index('suscripcion_id');
            $table->index('user_id');
            $table->index('created_at');
            
            // Foreign keys
            $table->foreign('suscripcion_id')->references('id')->on('suscripciones')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suscripcion_comision_audit');
    }
};
