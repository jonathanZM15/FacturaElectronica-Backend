<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('existencia_lotes', function (Blueprint $table) {
            $table->id(); // Representa id_existencia_lote
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('bodega_id');
            $table->unsignedBigInteger('lote_id');
            
            $table->decimal('stock_fisico', 14, 6)->default(0);
            $table->decimal('stock_reservado', 14, 6)->default(0);
            $table->decimal('stock_disponible', 14, 6)->default(0);
            
            $table->timestamps(); // fecha_actualizacion (updated_at) y created_at
            
            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('cascade');
            $table->foreign('bodega_id')->references('id')->on('bodegas')->onDelete('cascade');
            $table->foreign('lote_id')->references('id')->on('lotes')->onDelete('restrict');
            
            // Clave única compuesta: en una bodega, para un producto, no hay dos registros del mismo lote
            $table->unique(['producto_id', 'bodega_id', 'lote_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('existencia_lotes');
    }
};
