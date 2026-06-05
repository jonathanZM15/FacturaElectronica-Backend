<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comprobante extends Model
{
    use SoftDeletes;

    protected $table = 'comprobantes';

    protected $fillable = [
        'emisor_id',
        'establecimiento_id',
        'punto_emision_id',
        'cliente_id',
        'tipo_comprobante',
        'secuencial',
        'secuencial_formateado',
        'codigo_establecimiento',
        'punto_emision_codigo',
        'fecha_emision',
        'moneda',
        'subtotal_sin_impuestos',
        'subtotal_iva_0',
        'subtotal_iva',
        'subtotal_no_objeto',
        'subtotal_exento',
        'total_descuento',
        'total_ice',
        'total_irbpnr',
        'total_iva',
        'total_impuestos',
        'propina',
        'total',
        'clave_acceso',
        'estado_sri',
        'numero_autorizacion',
        'fecha_autorizacion',
        'xml_autorizado',
        'ambiente',
        'tipo_emision',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_autorizacion' => 'datetime',
        'subtotal_sin_impuestos' => 'decimal:2',
        'subtotal_iva_0' => 'decimal:2',
        'subtotal_iva' => 'decimal:2',
        'subtotal_no_objeto' => 'decimal:2',
        'subtotal_exento' => 'decimal:2',
        'total_descuento' => 'decimal:2',
        'total_ice' => 'decimal:2',
        'total_irbpnr' => 'decimal:2',
        'total_iva' => 'decimal:2',
        'total_impuestos' => 'decimal:2',
        'propina' => 'decimal:2',
        'total' => 'decimal:2',
        'xml_autorizado' => 'string',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'emisor_id');
    }

    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    public function puntoEmision()
    {
        return $this->belongsTo(PuntoEmision::class, 'punto_emision_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function detalles()
    {
        return $this->hasMany(ComprobanteDetalle::class, 'comprobante_id');
    }

    public function impuestos()
    {
        return $this->hasMany(ComprobanteImpuesto::class, 'comprobante_id');
    }
}
