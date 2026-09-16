<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistroOperativoDetalleLote extends Model
{
    use HasFactory;

    protected $table = 'registro_operativo_detalle_lotes';

    protected $fillable = [
        'registro_operativo_detalle_id',
        'lote_id',
        'cantidad',
        'observacion_lote_movimiento'
    ];

    protected $casts = [
        'cantidad' => 'decimal:6',
    ];

    public function detalle()
    {
        return $this->belongsTo(RegistroOperativoDetalle::class, 'registro_operativo_detalle_id');
    }

    public function lote()
    {
        return $this->belongsTo(Lote::class, 'lote_id');
    }
}
