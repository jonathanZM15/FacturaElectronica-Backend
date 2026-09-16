<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExistenciaLote extends Model
{
    use HasFactory;

    protected $table = 'existencia_lotes';

    protected $fillable = [
        'producto_id',
        'bodega_id',
        'lote_id',
        'stock_fisico',
        'stock_reservado',
        'stock_disponible',
    ];

    protected $casts = [
        'stock_fisico' => 'decimal:6',
        'stock_reservado' => 'decimal:6',
        'stock_disponible' => 'decimal:6',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function bodega()
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    public function lote()
    {
        return $this->belongsTo(Lote::class, 'lote_id');
    }
}
