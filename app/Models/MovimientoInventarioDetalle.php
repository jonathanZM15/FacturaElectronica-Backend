<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoInventarioDetalle extends Model
{
    use HasFactory;

    protected $table = 'movimiento_inventario_detalles';

    protected $fillable = [
        'movimiento_id',
        'producto_id',
        'cantidad',
        'codigo_lote',
        'numero_serie',
        'costo_unitario'
    ];

    protected $casts = [
        'cantidad' => 'decimal:6',
        'costo_unitario' => 'decimal:6',
    ];

    public function movimiento()
    {
        return $this->belongsTo(MovimientoInventario::class, 'movimiento_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
