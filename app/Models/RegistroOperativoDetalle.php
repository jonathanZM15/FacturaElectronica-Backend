<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistroOperativoDetalle extends Model
{
    use HasFactory;

    protected $table = 'registro_operativo_detalles';

    protected $fillable = [
        'registro_operativo_id',
        'producto_id',
        'cantidad',
        'cantidad_sobrante',
        'tipo_incidencia',
        'observacion_detalle'
    ];

    protected $casts = [
        'cantidad' => 'decimal:6',
        'cantidad_sobrante' => 'decimal:6',
    ];

    public function movimiento()
    {
        return $this->belongsTo(RegistroOperativoMovimiento::class, 'registro_operativo_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function lotes()
    {
        return $this->hasMany(RegistroOperativoDetalleLote::class, 'registro_operativo_detalle_id');
    }

    public function series()
    {
        return $this->hasMany(RegistroOperativoDetalleSerie::class, 'registro_operativo_detalle_id');
    }
}
