<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistroOperativoDetalleSerie extends Model
{
    use HasFactory;

    protected $table = 'registro_operativo_detalle_series';

    protected $fillable = [
        'registro_operativo_detalle_id',
        'serie_id',
        'observacion_serie_movimiento'
    ];

    public function detalle()
    {
        return $this->belongsTo(RegistroOperativoDetalle::class, 'registro_operativo_detalle_id');
    }

    public function serie()
    {
        return $this->belongsTo(Serie::class, 'serie_id');
    }
}
