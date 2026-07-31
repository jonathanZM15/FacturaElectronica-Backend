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
        Schema::create('movimiento_inventario_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('movimiento_id');
            $table->unsignedBigInteger('producto_id');
            $table->decimal('cantidad', 14, 6);
            $table->string('codigo_lote')->nullable();
            $table->string('numero_serie')->nullable();
            $table->decimal('costo_unitario', 14, 6)->nullable();
            $table->timestamps();

            $table->foreign('movimiento_id')->references('id')->on('movimientos_inventario')->onDelete('cascade');
            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimiento_inventario_detalles');
    }
};
