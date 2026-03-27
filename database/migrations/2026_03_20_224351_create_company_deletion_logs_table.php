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
        Schema::create('company_deletion_logs', function (Blueprint $table) {
            $table->id();
            
            // Reference al emisor
            $table->unsignedBigInteger('company_id');
            
            // Tipo de evento: warning_sent, final_notice_sent, manual_deletion, auto_deletion, restored
            $table->enum('action_type', ['warning_sent', 'final_notice_sent', 'manual_deletion', 'auto_deletion', 'restored'])->index();
            
            // Usuario que ejecutó la acción (si aplica)
            $table->unsignedBigInteger('user_id')->nullable();
            
            // Descripción de la acción
            $table->text('description')->nullable();
            
            // Información del backup (ruta del archivo, fecha de generación)
            $table->string('backup_file_path')->nullable();
            
            // Dirección IP de donde se ejecutó
            $table->string('ip_address')->nullable();
            
            // User Agent (navegador/cliente)
            $table->text('user_agent')->nullable();
            
            // JSON con datos adicionales
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Índices para búsquedas rápidas
            $table->index(['company_id', 'action_type']);
            $table->index('created_at');
            
            // Foreign keys
            $table->foreign('company_id')->references('id')->on('emisores')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_deletion_logs');
    }
};
