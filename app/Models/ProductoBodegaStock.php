<?php

namespace App\Models;

use App\Enums\BaseComparacionStock;
use Illuminate\Database\Eloquent\Model;

class ProductoBodegaStock extends Model
{
    protected $table = 'producto_bodega_stock';

    protected $fillable = [
        'producto_id',
        'bodega_id',
        'stock_minimo',
        'stock_maximo',
        'base_comparacion',
        'activo',
        'observacion',
        'stock_fisico',
        'stock_disponible',
        'stock_reservado',
    ];

    protected $casts = [
        'stock_minimo' => 'decimal:6',
        'stock_maximo' => 'decimal:6',
        'base_comparacion' => BaseComparacionStock::class,
        'activo' => 'boolean',
        'stock_fisico' => 'decimal:6',
        'stock_disponible' => 'decimal:6',
        'stock_reservado' => 'decimal:6',
    ];

    public function producto() { return $this->belongsTo(\App\Models\Producto::class); }
    public function bodega() { return $this->belongsTo(\App\Models\Bodega::class); }
}
