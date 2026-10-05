<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('compra_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            
            $table->foreignId('producto_id')->nullable()->constrained('productos');
            $table->string('codigo_principal', 50)->nullable();
            $table->string('descripcion', 300);
            
            $table->decimal('cantidad', 12, 4);
            $table->decimal('precio_unitario', 12, 4); // Costo de compra
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('precio_total_sin_impuesto', 12, 2);
            
            // Impuestos
            $table->string('codigo_impuesto', 5)->default('2'); // IVA
            $table->string('codigo_porcentaje', 5); // 0=0%, 2=12%, etc.
            $table->decimal('tarifa', 5, 2); // 0, 12, 15
            $table->decimal('base_imponible', 12, 2);
            $table->decimal('valor_impuesto', 12, 2);
            
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('compra_detalles');
    }
};
