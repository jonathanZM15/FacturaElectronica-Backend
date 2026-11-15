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

    protected static function booted(): void
    {
        static::saving(function (self $log) {
            foreach (['mensaje', 'solicitud_payload', 'respuesta_payload', 'detalles'] as $field) {
                $clean = \App\Services\SensitiveDataSanitizer::clean($log->$field);
                $log->$field = $field !== 'detalles' && is_array($clean)
                    ? json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $clean;
            }
        });
    }

    public function comprobante()
    {
        return $this->belongsTo(Comprobante::class, 'comprobante_id');
    }
}
