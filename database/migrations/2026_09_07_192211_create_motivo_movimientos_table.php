<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motivos_movimiento', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('emisor_id');
            $table->string('codigo', 20); // Ej: TRA-01
            $table->string('descripcion'); // Ej: Reabastecimiento
            $table->string('tipo_movimiento'); // Enum (MOV-06, etc.)
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('emisor_id')->references('id')->on('emisores')->onDelete('cascade');
            $table->unique(['emisor_id', 'codigo']);
        });

        // Add foreign key to registros operativos
        Schema::table('registros_operativos_movimiento', function (Blueprint $table) {
            $table->foreign('motivo_id')->references('id')->on('motivos_movimiento')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('registros_operativos_movimiento', function (Blueprint $table) {
            $table->dropForeign(['motivo_id']);
        });
        Schema::dropIfExists('motivos_movimiento');
    }
};
