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
        Schema::create('producto_bodega_stock', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('bodega_id');
            $table->decimal('stock_minimo', 14, 6)->nullable();
            $table->decimal('stock_maximo', 14, 6)->nullable();
            $table->string('base_comparacion')->default('FISICO');
            $table->boolean('activo')->default(true);
            $table->string('observacion')->nullable();
            $table->timestamps();

            // Llaves foráneas
            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('cascade');
            $table->foreign('bodega_id')->references('id')->on('bodegas')->onDelete('cascade');
            
            // Un producto solo debe tener un parámetro de configuración por bodega
            $table->unique(['producto_id', 'bodega_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('producto_bodega_stock');
    }
};
