<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emisor_id')->constrained('emisores')->cascadeOnDelete();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnDelete();
            $table->foreignId('punto_emision_id')->constrained('puntos_emision')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();

            $table->string('tipo_comprobante', 20);
            $table->unsignedInteger('secuencial');
            $table->string('secuencial_formateado', 9);
            $table->string('codigo_establecimiento', 3);
            $table->string('punto_emision_codigo', 3);
            $table->date('fecha_emision');
            $table->string('moneda', 10)->default('USD');

            $table->decimal('subtotal_sin_impuestos', 18, 2)->default(0);
            $table->decimal('subtotal_iva_0', 18, 2)->default(0);
            $table->decimal('subtotal_iva', 18, 2)->default(0);
            $table->decimal('subtotal_no_objeto', 18, 2)->default(0);
            $table->decimal('subtotal_exento', 18, 2)->default(0);
            $table->decimal('total_descuento', 18, 2)->default(0);
            $table->decimal('total_ice', 18, 2)->default(0);
            $table->decimal('total_irbpnr', 18, 2)->default(0);
            $table->decimal('total_iva', 18, 2)->default(0);
            $table->decimal('total_impuestos', 18, 2)->default(0);
            $table->decimal('propina', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);

            $table->string('clave_acceso', 49)->nullable();
            $table->string('estado_sri', 20)->default('CREADA');
            $table->string('numero_autorizacion', 49)->nullable();
            $table->dateTime('fecha_autorizacion')->nullable();
            $table->string('ambiente', 15)->default('PRODUCCION');
            $table->string('tipo_emision', 20)->default('NORMAL');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['emisor_id', 'estado_sri']);
            $table->index('clave_acceso');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes');
    }
};
