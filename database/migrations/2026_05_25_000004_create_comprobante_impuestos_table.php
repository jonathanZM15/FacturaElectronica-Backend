<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobante_impuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_id')->constrained('comprobantes')->cascadeOnDelete();
            $table->foreignId('comprobante_detalle_id')->nullable()->constrained('comprobante_detalles')->nullOnDelete();
            $table->foreignId('tipo_impuesto_id')->constrained('tipos_impuesto')->restrictOnDelete();
            $table->decimal('base_imponible', 18, 2);
            $table->decimal('tarifa', 6, 4);
            $table->decimal('valor', 18, 2);
            $table->timestamps();

            $table->index(['comprobante_id', 'tipo_impuesto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_impuestos');
    }
};
