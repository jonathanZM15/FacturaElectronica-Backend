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
        Schema::create('pos_turnos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('caja_id');
            $table->unsignedBigInteger('usuario_id');
            $table->dateTime('fecha_apertura');
            $table->dateTime('fecha_cierre')->nullable();
            $table->decimal('saldo_inicial', 10, 2);
            $table->decimal('saldo_final', 10, 2)->nullable();
            $table->enum('estado', ['Abierto', 'Cerrado'])->default('Abierto');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('caja_id')->references('id')->on('cajas')->onDelete('cascade');
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_turnos');
    }
};
