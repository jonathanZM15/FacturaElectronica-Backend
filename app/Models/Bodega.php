<?php

namespace App\Models;

use App\Enums\TipoBodega;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bodega extends Model
{
    use SoftDeletes;

    protected $table = 'bodegas';

    protected $fillable = [
        'establecimiento_id',
        'codigo',
        'nombre',
        'tipo',
        'permite_venta',
        'creador_id',
    ];

    protected $casts = [
        'tipo' => TipoBodega::class,
        'permite_venta' => 'boolean',
    ];

    protected $appends = ['emisor_id'];

    public function getEmisorIdAttribute(): ?int
    {
        return $this->establecimiento?->emisor_id;
    }

    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creador_id');
    }

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'producto_bodega_stock')
            ->withPivot([
                'stock_minimo',
                'stock_maximo',
                'base_comparacion',
                'activo',
                'observacion',
                'fecha_registro',
                'stock_fisico',
                'stock_disponible',
                'stock_reservado',
            ])
            ->withTimestamps();
    }
}
