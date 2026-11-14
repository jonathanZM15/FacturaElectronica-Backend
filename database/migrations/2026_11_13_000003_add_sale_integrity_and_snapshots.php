<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->uuid('operacion_id')->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('bodega_id')->nullable()->constrained('bodegas')->restrictOnDelete();
            $table->foreignId('registro_operativo_id')->nullable()->constrained('registros_operativos_movimiento')->restrictOnDelete();
            foreach (['cliente_snapshot', 'emisor_snapshot', 'establecimiento_snapshot', 'pagos', 'informacion_adicional'] as $name) {
                $table->json($name)->nullable();
            }
            $table->unique(['emisor_id', 'operacion_id']);
        });
        Schema::table('comprobante_detalles', function (Blueprint $table) {
            $table->string('codigo_principal')->nullable();
            $table->string('codigo_auxiliar')->nullable();
        });
        Schema::table('comprobante_impuestos', function (Blueprint $table) {
            $table->unsignedInteger('codigo_impuesto')->nullable();
            $table->unsignedInteger('codigo_porcentaje')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('comprobante_impuestos', fn (Blueprint $table) => $table->dropColumn(['codigo_impuesto', 'codigo_porcentaje']));
        Schema::table('comprobante_detalles', fn (Blueprint $table) => $table->dropColumn(['codigo_principal', 'codigo_auxiliar']));
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropUnique(['emisor_id', 'operacion_id']);
            foreach (['created_by_id', 'bodega_id', 'registro_operativo_id'] as $name) $table->dropConstrainedForeignId($name);
            $table->dropColumn(['operacion_id', 'request_hash', 'cliente_snapshot', 'emisor_snapshot', 'establecimiento_snapshot', 'pagos', 'informacion_adicional']);
        });
    }
};
