import os
import glob

backend_path = r"C:\Users\CompuStore\Desktop\dos sistemas\tesis\FacturaElectronica-Backend"

# COMPRAS MIGRATION
migs_compras = glob.glob(os.path.join(backend_path, "database", "migrations", "*_create_compras_table.php"))
if migs_compras:
    with open(migs_compras[0], 'w', encoding='utf-8') as f:
        f.write("""<?php
use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

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
            $table->foreignId('movimiento_inventario_id')->nullable()->constrained('movimientos_inventario');
            
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
""")

# COMPRA_DETALLES MIGRATION
migs_detalles = glob.glob(os.path.join(backend_path, "database", "migrations", "*_create_compra_detalles_table.php"))
if migs_detalles:
    with open(migs_detalles[0], 'w', encoding='utf-8') as f:
        f.write("""<?php
use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

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
""")

# COMPRA MODEL
with open(os.path.join(backend_path, "app", "Models", "Compra.php"), 'w', encoding='utf-8') as f:
    f.write("""<?php
namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;

class Compra extends Model {
    use HasFactory;

    protected $table = 'compras';

    protected $fillable = [
        'emisor_id', 'establecimiento_id', 'proveedor_id', 'tipo_ingreso',
        'comprobante_sri_id', 'numero_comprobante', 'clave_acceso_proveedor',
        'fecha_emision', 'fecha_registro', 'subtotal_0', 'subtotal_12',
        'subtotal_no_objeto_iva', 'subtotal_exento_iva', 'subtotal_sin_impuestos',
        'total_descuento', 'total_iva', 'importe_total', 'movimiento_inventario_id',
        'observaciones', 'estado', 'created_by', 'updated_by'
    ];

    public function detalles() {
        return $this->hasMany(CompraDetalle::class, 'compra_id');
    }

    public function proveedor() {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }
}
""")

# COMPRA DETALLE MODEL
with open(os.path.join(backend_path, "app", "Models", "CompraDetalle.php"), 'w', encoding='utf-8') as f:
    f.write("""<?php
namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;

class CompraDetalle extends Model {
    protected $table = 'compra_detalles';

    protected $fillable = [
        'compra_id', 'producto_id', 'codigo_principal', 'descripcion',
        'cantidad', 'precio_unitario', 'descuento', 'precio_total_sin_impuesto',
        'codigo_impuesto', 'codigo_porcentaje', 'tarifa', 'base_imponible', 'valor_impuesto'
    ];
}
""")

print("Compras models and migrations generated.")
