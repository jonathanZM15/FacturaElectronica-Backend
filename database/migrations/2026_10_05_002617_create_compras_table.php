<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emisor_id')->constrained('emisores');
            $table->foreignId('establecimiento_id')->nullable()->constrained('establecimientos');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores');
            
            // TIPOS: 'FACTURA_PROVEEDOR', 'LIQUIDACION_COMPRA', 'SIN_COMPROBANTE'
            $table->string('tipo_ingreso', 30);
            
            // Reference to the emitted electronic document (if Liquidacion de Compra)
            $table->foreignId('comprobante_sri_id')->nullable()->constrained('comprobantes');
            
            // Si es factura de proveedor, este es el numero físico/electrónico del proveedor (ej. 001-001-000000123)
            $table->string('numero_comprobante', 50)->nullable();
            
            // Autorización del SRI si la factura del proveedor era electrónica
            $table->string('clave_acceso_proveedor', 49)->nullable();
            
            $table->date('fecha_emision');
            $table->date('fecha_registro')->useCurrent();
            
            // Totales
            $table->decimal('subtotal_0', 12, 2)->default(0);
            $table->decimal('subtotal_12', 12, 2)->default(0);
            $table->decimal('subtotal_no_objeto_iva', 12, 2)->default(0);
            $table->decimal('subtotal_exento_iva', 12, 2)->default(0);
            $table->decimal('subtotal_sin_impuestos', 12, 2)->default(0);
            $table->decimal('total_descuento', 12, 2)->default(0);
            $table->decimal('total_iva', 12, 2)->default(0);
            $table->decimal('importe_total', 12, 2)->default(0);
            
            // Reference to inventory movement
            $table->foreignId('registro_operativo_id')->nullable()->constrained('registros_operativos_movimiento');
            
            $table->text('observaciones')->nullable();
            
            // ESTADOS: 'BORRADOR', 'APROBADA', 'ANULADA'
            $table->string('estado', 20)->default('APROBADA');
            
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('compras');
    }
};
