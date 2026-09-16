<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->id(); // Representa id_serie
            $table->unsignedBigInteger('producto_id');
            $table->string('numero_serie', 100);
            $table->unsignedBigInteger('bodega_actual_id')->nullable();
            $table->string('estado', 50); // DISPONIBLE, BLOQUEADA, RESERVADA, etc.
            $table->date('fecha_fabricacion')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->string('numero_lote_referencial', 100)->nullable();
            $table->boolean('reacondicionado')->default(false);
            $table->text('observacion')->nullable();
            
            $table->unsignedBigInteger('usuario_registro_id')->nullable();
            $table->timestamp('fecha_actualizacion_manual_estado')->nullable();
            $table->unsignedBigInteger('usuario_actualizacion_manual_id')->nullable();
            
            $table->timestamps(); // fecha_registro y fecha_actualizacion
            $table->softDeletes();

            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('restrict');
            $table->foreign('bodega_actual_id')->references('id')->on('bodegas')->onDelete('restrict');
            $table->foreign('usuario_registro_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('usuario_actualizacion_manual_id', 'fk_s_usuario_manual')->references('id')->on('users')->onDelete('set null');
            
            // Clave única compuesta: Un mismo producto no puede tener dos series con el mismo número
            $table->unique(['producto_id', 'numero_serie']);
        });

        // Actualizar la tabla de sub-detalle series para añadir la clave foránea
        Schema::table('registro_operativo_detalle_series', function (Blueprint $table) {
            $table->foreign('serie_id')->references('id')->on('series')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('registro_operativo_detalle_series', function (Blueprint $table) {
            $table->dropForeign(['serie_id']);
        });
        Schema::dropIfExists('series');
    }
};
