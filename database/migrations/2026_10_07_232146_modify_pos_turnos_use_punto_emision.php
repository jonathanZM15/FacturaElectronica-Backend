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
        Schema::table('pos_turnos', function (Blueprint $table) {
            // Eliminar relacion con cajas
            $table->dropForeign(['caja_id']);
            $table->dropColumn('caja_id');
            
            // Agregar relacion con punto_emision
            $table->unsignedBigInteger('punto_emision_id')->after('id');
            $table->foreign('punto_emision_id')->references('id')->on('puntos_emision')->onDelete('cascade');
        });

        // Podemos eliminar la tabla cajas ya que no se usara
        Schema::dropIfExists('cajas');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->string('nombre', 100);
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->foreign('establecimiento_id')->references('id')->on('establecimientos')->onDelete('cascade');
        });

        Schema::table('pos_turnos', function (Blueprint $table) {
            $table->dropForeign(['punto_emision_id']);
            $table->dropColumn('punto_emision_id');
            
            $table->unsignedBigInteger('caja_id')->after('id');
            $table->foreign('caja_id')->references('id')->on('cajas')->onDelete('cascade');
        });
    }
};
