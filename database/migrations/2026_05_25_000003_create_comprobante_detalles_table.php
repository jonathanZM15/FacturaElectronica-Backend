<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobante_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_id')->constrained('comprobantes')->cascadeOnDelete();
            $table->unsignedBigInteger('producto_id')->nullable();
            $table->string('descripcion', 500);
            $table->decimal('cantidad', 18, 6);
            $table->decimal('precio_unitario', 18, 6);
            $table->decimal('descuento', 18, 6)->default(0);
            $table->decimal('subtotal', 18, 2);
            $table->timestamps();

            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_detalles');
    }
};
