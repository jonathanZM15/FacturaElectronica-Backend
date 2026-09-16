<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Eliminar tablas antiguas (refactor destructivo autorizado)
        Schema::dropIfExists('movimiento_inventario_detalles');
        Schema::dropIfExists('movimientos_inventario');

        // 2. Crear nueva tabla principal (Encabezado)
        Schema::create('registros_operativos_movimiento', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('emisor_id');
            $table->string('numero', 50)->unique(); // Ej: MOV06-000001
            $table->timestamp('fecha');
            $table->string('tipo_movimiento'); // Enum (MOV_01 a MOV_20)
            $table->string('estado'); // BORRADOR, EN_PROCESO, CONFIRMADO
            
            // Bodegas y establecimientos
            $table->unsignedBigInteger('establecimiento_origen_id')->nullable();
            $table->unsignedBigInteger('bodega_origen_id')->nullable();
            $table->unsignedBigInteger('establecimiento_destino_id')->nullable();
            $table->unsignedBigInteger('bodega_destino_id')->nullable();
            $table->unsignedBigInteger('bodega_transito_id')->nullable();
            $table->unsignedBigInteger('bodega_recepcion_id')->nullable();
            $table->unsignedBigInteger('bodega_incidencia_id')->nullable();

            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('motivo_id')->nullable(); // FK a nueva tabla de motivos
            $table->text('observacion')->nullable();
            
            // Campos específicos de transferencia (MOV-07/08)
            $table->string('estado_operativo_transferencia')->nullable(); 
            $table->string('estado_operativo_recepcion')->nullable();
            $table->string('origen_transferencia')->nullable(); // "Directa" o referencia
            $table->unsignedBigInteger('movimiento_origen_id')->nullable(); // Para MOV-08 referencia a MOV-07

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('emisor_id')->references('id')->on('emisores')->onDelete('cascade');
            $table->foreign('establecimiento_origen_id')->references('id')->on('establecimientos')->onDelete('restrict');
            $table->foreign('bodega_origen_id')->references('id')->on('bodegas')->onDelete('restrict');
            $table->foreign('establecimiento_destino_id')->references('id')->on('establecimientos')->onDelete('restrict');
            $table->foreign('bodega_destino_id')->references('id')->on('bodegas')->onDelete('restrict');
            $table->foreign('bodega_transito_id')->references('id')->on('bodegas')->onDelete('restrict');
            $table->foreign('bodega_recepcion_id')->references('id')->on('bodegas')->onDelete('restrict');
            $table->foreign('bodega_incidencia_id')->references('id')->on('bodegas')->onDelete('restrict');
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('movimiento_origen_id')->references('id')->on('registros_operativos_movimiento')->onDelete('restrict');
        });

        // 3. Crear tabla detalle principal (productos)
        Schema::create('registro_operativo_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('registro_operativo_id');
            $table->unsignedBigInteger('producto_id');
            $table->decimal('cantidad', 14, 6);
            $table->decimal('cantidad_sobrante', 14, 6)->default(0); // Para MOV-08
            $table->string('tipo_incidencia')->nullable(); // Para MOV-08
            $table->text('observacion_detalle')->nullable();
            $table->timestamps();

            $table->foreign('registro_operativo_id')->references('id')->on('registros_operativos_movimiento')->onDelete('cascade');
            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('restrict');
        });

        // 4. Crear tabla sub-detalle lotes
        Schema::create('registro_operativo_detalle_lotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('registro_operativo_detalle_id');
            $table->unsignedBigInteger('lote_id'); // Será FK a lotes cuando se cree
            $table->decimal('cantidad', 14, 6);
            $table->text('observacion_lote_movimiento')->nullable();
            $table->timestamps();

            $table->foreign('registro_operativo_detalle_id', 'fk_rod_lotes_rod_id')->references('id')->on('registro_operativo_detalles')->onDelete('cascade');
        });

        // 5. Crear tabla sub-detalle series
        Schema::create('registro_operativo_detalle_series', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('registro_operativo_detalle_id');
            $table->unsignedBigInteger('serie_id'); // Será FK a series cuando se cree
            $table->text('observacion_serie_movimiento')->nullable();
            $table->timestamps();

            $table->foreign('registro_operativo_detalle_id', 'fk_rod_series_rod_id')->references('id')->on('registro_operativo_detalles')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_operativo_detalle_series');
        Schema::dropIfExists('registro_operativo_detalle_lotes');
        Schema::dropIfExists('registro_operativo_detalles');
        Schema::dropIfExists('registros_operativos_movimiento');

        // Restaurar tablas previas genéricas
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
        });

        Schema::create('movimiento_inventario_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('movimiento_id');
            $table->unsignedBigInteger('producto_id');
            $table->decimal('cantidad', 14, 6);
            $table->string('codigo_lote')->nullable();
            $table->string('numero_serie')->nullable();
            $table->decimal('costo_unitario', 14, 6)->nullable();
            $table->timestamps();
        });
    }
};
