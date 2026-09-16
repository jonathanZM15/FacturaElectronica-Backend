<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kardex', function (Blueprint $table) {
            $table->id(); // id_kardex
            $table->timestamp('fecha_hora');
            
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('bodega_id');
            
            $table->string('tipo_movimiento'); // ej. MOV_06_TRANSFERENCIA_INTERNA
            
            $table->string('documento_origen_tipo', 100); // ej. 'RegistroOperativo', 'Factura', 'Compra'
            $table->unsignedBigInteger('documento_origen_id')->nullable();
            $table->string('numero_documento', 50)->nullable(); // ej. MOV06-000001
            
            $table->decimal('entrada', 14, 6)->default(0);
            $table->decimal('salida', 14, 6)->default(0);
            $table->decimal('saldo', 14, 6)->default(0); // Representa el stock_fisico después del movimiento
            
            $table->unsignedBigInteger('usuario_id')->nullable();
            
            // Inmutable: no tiene timestamps ni softDeletes
            
            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('restrict');
            $table->foreign('bodega_id')->references('id')->on('bodegas')->onDelete('restrict');
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kardex');
    }
};
