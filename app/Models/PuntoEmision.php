<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PuntoEmision extends Model
{
    use SoftDeletes;

    protected $table = 'puntos_emision';

    protected $fillable = [
        'company_id',
        'establecimiento_id',
        'user_id',
        'codigo',
        'estado',
        'nombre',
        'secuencial_factura',
        'secuencial_liquidacion_compra',
        'secuencial_nota_credito',
        'secuencial_nota_debito',
        'secuencial_guia_remision',
        'secuencial_retencion',
        'secuencial_proforma',
        'bloqueo_edicion_produccion',
        'bloqueo_edicion_produccion_at',
    ];

    protected $casts = [
        'bloqueo_edicion_produccion' => 'boolean',
        'bloqueo_edicion_produccion_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relación: Un punto de emisión pertenece a una compañía
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Relación: Un punto de emisión pertenece a un establecimiento
     */
    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    /**
     * Relación: Un punto de emisión está asociado a un usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
