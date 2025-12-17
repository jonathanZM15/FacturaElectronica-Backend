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
        Schema::create('suscripcion_estado_audit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('suscripcion_id');
            $table->string('estado_anterior', 60);
            $table->string('estado_nuevo', 60);
            $table->enum('tipo_transicion', ['Manual', 'Automatico']);
            $table->string('motivo', 255)->nullable(); // Descripción de por qué cambió
            $table->unsignedBigInteger('user_id')->nullable(); // Null si es automático
            $table->string('user_role', 50)->nullable(); // Null si es automático
            $table->string('ip_address', 45)->nullable(); // Null si es automático
            $table->string('user_agent', 255)->nullable(); // Null si es automático
            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('suscripcion_id')
                ->references('id')
                ->on('suscripciones')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Índices
            $table->index(['suscripcion_id', 'created_at']);
            $table->index('tipo_transicion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suscripcion_estado_audit');
    }
};
