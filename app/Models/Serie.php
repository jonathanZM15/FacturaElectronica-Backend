<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Serie extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'series';

    protected $fillable = [
        'producto_id',
        'numero_serie',
        'bodega_actual_id',
        'estado',
        'fecha_fabricacion',
        'fecha_vencimiento',
        'numero_lote_referencial',
        'reacondicionado',
        'observacion',
        'usuario_registro_id',
        'fecha_actualizacion_manual_estado',
        'usuario_actualizacion_manual_id'
    ];

    protected $casts = [
        'fecha_fabricacion' => 'date',
        'fecha_vencimiento' => 'date',
        'reacondicionado' => 'boolean',
        'fecha_actualizacion_manual_estado' => 'datetime',
        'estado' => \App\Enums\EstadoSerie::class,
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function bodegaActual()
    {
        return $this->belongsTo(Bodega::class, 'bodega_actual_id');
    }

    public function usuarioRegistro()
    {
        return $this->belongsTo(User::class, 'usuario_registro_id');
    }

    public function usuarioActualizacionManual()
    {
        return $this->belongsTo(User::class, 'usuario_actualizacion_manual_id');
    }
}
