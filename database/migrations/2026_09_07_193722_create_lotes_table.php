<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotes', function (Blueprint $table) {
            $table->id(); // Representa id_lote
            $table->unsignedBigInteger('producto_id'); // Referencia al producto
            $table->string('numero_lote', 100);
            $table->date('fecha_fabricacion')->nullable();
            $table->date('fecha_vencimiento');
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('usuario_registro_id')->nullable(); // id_usuario_registro
            $table->timestamps(); // Crea created_at (fecha_registro) y updated_at
            $table->softDeletes();

            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('restrict');
            $table->foreign('usuario_registro_id')->references('id')->on('users')->onDelete('set null');
            
            // Clave única compuesta: Un mismo producto no puede tener dos lotes con el mismo número
            $table->unique(['producto_id', 'numero_lote']);
        });

        // Actualizar la tabla de sub-detalle lotes para añadir la clave foránea
        Schema::table('registro_operativo_detalle_lotes', function (Blueprint $table) {
            $table->foreign('lote_id')->references('id')->on('lotes')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('registro_operativo_detalle_lotes', function (Blueprint $table) {
            $table->dropForeign(['lote_id']);
        });
        Schema::dropIfExists('lotes');
    }
};
