<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Compra extends Model {
    use HasFactory;

    protected $table = 'compras';

    protected $fillable = [
        'emisor_id', 'establecimiento_id', 'proveedor_id', 'tipo_ingreso',
        'comprobante_sri_id', 'numero_comprobante', 'clave_acceso_proveedor',
        'fecha_emision', 'fecha_registro', 'subtotal_0', 'subtotal_12',
        'subtotal_no_objeto_iva', 'subtotal_exento_iva', 'subtotal_sin_impuestos',
        'total_descuento', 'total_iva', 'importe_total', 'registro_operativo_id',
        'observaciones', 'estado', 'created_by', 'updated_by'
    ];

    public function detalles() {
        return $this->hasMany(CompraDetalle::class, 'compra_id');
    }

    public function proveedor() {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }
}
