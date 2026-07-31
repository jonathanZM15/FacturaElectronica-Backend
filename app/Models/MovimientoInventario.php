<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\TipoMovimientoInventario;

class MovimientoInventario extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'emisor_id',
        'bodega_origen_id',
        'bodega_destino_id',
        'tipo_movimiento',
        'estado',
        'observacion',
        'usuario_id'
    ];

    protected $casts = [
        'tipo_movimiento' => TipoMovimientoInventario::class,
    ];

    public function origen()
    {
        return $this->belongsTo(Bodega::class, 'bodega_origen_id');
    }

    public function destino()
    {
        return $this->belongsTo(Bodega::class, 'bodega_destino_id');
    }

    public function detalles()
    {
        return $this->hasMany(MovimientoInventarioDetalle::class, 'movimiento_id');
    }
}
