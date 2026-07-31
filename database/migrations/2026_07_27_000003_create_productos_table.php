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
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('emisor_id');
            
            // 1. Datos generales
            $table->string('codigo');
            $table->string('codigo_auxiliar')->nullable();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('foto')->nullable();

            // 2. Precios
            $table->decimal('precio_1', 14, 6);
            $table->decimal('precio_2', 14, 6)->nullable();
            $table->decimal('precio_3', 14, 6)->nullable();
            $table->integer('precio_por_defecto')->default(1);

            // 3. Impuestos
            $table->string('tipo_iva');
            $table->boolean('impuesto_ice')->default(false);
            $table->decimal('porcentaje_ice', 14, 6)->nullable();
            $table->decimal('irbpnr', 14, 6)->nullable();

            // 4. Configuración del producto
            $table->string('tipo');
            $table->string('tipo_control_inventario');
            $table->boolean('permite_venta');
            $table->boolean('permite_compra');
            $table->boolean('uso_interno');
            $table->boolean('permite_exhibicion');
            $table->unsignedBigInteger('categoria_id')->nullable();

            // 5. Configuración relacionada a inventario
            $table->string('unidad_medida')->nullable();

            // 6. Otros datos
            $table->decimal('subsidio_unitario', 14, 6)->nullable();
            $table->string('ubicacion_referencial')->nullable();

            // Configuración Comercial
            $table->boolean('seleccionable_venta_suspendida')->default(false);
            $table->string('tipo_entrega')->nullable();
            $table->string('tipo_bodega_salida')->nullable();
            $table->boolean('requiere_preparacion')->default(false);
            $table->boolean('seleccion_avanzada_bodega_salida')->default(false);
            $table->boolean('permite_devolucion')->default(true);
            $table->integer('tiempo_garantia_devolucion')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Llaves foráneas e índices
            $table->foreign('emisor_id')->references('id')->on('emisores')->onDelete('cascade');
            $table->foreign('categoria_id')->references('id')->on('categorias')->onDelete('set null');
            
            // Evitar códigos duplicados por empresa
            $table->unique(['emisor_id', 'codigo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
