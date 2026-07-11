<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComprobanteSriLog extends Model
{
    protected $table = 'comprobante_sri_logs';

    protected $fillable = [
        'comprobante_id',
        'etapa',
        'estado',
        'codigo',
        'mensaje',
        'solicitud_payload',
        'respuesta_payload',
        'detalles',
    ];

    protected $casts = [
        'detalles' => 'array',
    ];

    public function comprobante()
    {
        return $this->belongsTo(Comprobante::class, 'comprobante_id');
    }
}