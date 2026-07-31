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
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('emisor_id');
            $table->unsignedBigInteger('bodega_origen_id')->nullable();
            $table->unsignedBigInteger('bodega_destino_id')->nullable();
            $table->string('tipo_movimiento');
            $table->string('estado')->default('COMPLETADO');
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('emisor_id')->references('id')->on('emisores')->onDelete('cascade');
            $table->foreign('bodega_origen_id')->references('id')->on('bodegas')->onDelete('restrict');
            $table->foreign('bodega_destino_id')->references('id')->on('bodegas')->onDelete('restrict');
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
