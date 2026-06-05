<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComprobanteDetalle extends Model
{
    protected $table = 'comprobante_detalles';

    protected $fillable = [
        'comprobante_id',
        'producto_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'descuento',
        'subtotal',
    ];

    protected $casts = [
        'cantidad' => 'decimal:6',
        'precio_unitario' => 'decimal:6',
        'descuento' => 'decimal:6',
        'subtotal' => 'decimal:2',
    ];

    public function comprobante()
    {
        return $this->belongsTo(Comprobante::class, 'comprobante_id');
    }

    public function impuestos()
    {
        return $this->hasMany(ComprobanteImpuesto::class, 'comprobante_detalle_id');
    }
}
