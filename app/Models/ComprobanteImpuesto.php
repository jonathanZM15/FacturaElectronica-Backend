<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComprobanteImpuesto extends Model
{
    protected $table = 'comprobante_impuestos';

    protected $fillable = [
        'comprobante_id',
        'comprobante_detalle_id',
        'tipo_impuesto_id',
        'base_imponible',
        'tarifa',
        'valor',
    ];

    protected $casts = [
        'base_imponible' => 'decimal:2',
        'tarifa' => 'decimal:4',
        'valor' => 'decimal:2',
    ];

    public function comprobante()
    {
        return $this->belongsTo(Comprobante::class, 'comprobante_id');
    }

    public function detalle()
    {
        return $this->belongsTo(ComprobanteDetalle::class, 'comprobante_detalle_id');
    }

    public function tipoImpuesto()
    {
        return $this->belongsTo(TipoImpuesto::class, 'tipo_impuesto_id');
    }
}
