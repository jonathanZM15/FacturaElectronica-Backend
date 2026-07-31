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
        'emisor_id',
        'nombre',
        'tipo',
        'creador_id',
    ];

    protected $casts = [
        'tipo' => TipoBodega::class,
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'emisor_id');
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
            ])
            ->withTimestamps();
    }
}
