<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobante_sri_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_id')->constrained('comprobantes')->cascadeOnDelete();
            $table->string('etapa', 50);
            $table->string('estado', 30)->nullable();
            $table->string('codigo', 50)->nullable();
            $table->text('mensaje')->nullable();
            $table->longText('solicitud_payload')->nullable();
            $table->longText('respuesta_payload')->nullable();
            $table->json('detalles')->nullable();
            $table->timestamps();

            $table->index(['comprobante_id', 'etapa']);
            $table->index(['comprobante_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_sri_logs');
    }
};