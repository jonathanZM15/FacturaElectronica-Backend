<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompraDetalle extends Model {
    protected $table = 'compra_detalles';

    protected $fillable = [
        'compra_id', 'producto_id', 'codigo_principal', 'descripcion',
        'cantidad', 'precio_unitario', 'descuento', 'precio_total_sin_impuesto',
        'codigo_impuesto', 'codigo_porcentaje', 'tarifa', 'base_imponible', 'valor_impuesto'
    ];
}
