<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PuntoEmision extends Model
{
    use SoftDeletes;

    private const MAX_SECUENCIAL = 999999999;

    protected $table = 'puntos_emision';

    protected $fillable = [
        'emisor_id',
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
        return $this->belongsTo(Company::class, 'emisor_id');
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

    public function nextSecuencialFactura(): array
    {
        return DB::transaction(function () {
            $row = self::where('id', $this->id)->lockForUpdate()->first();
            if (!$row) {
                throw new \RuntimeException('Punto de emision no encontrado para secuencial.');
            }

            $current = (int) $row->secuencial_factura;
            if ($current <= 0 || $current > self::MAX_SECUENCIAL) {
                throw new \RuntimeException('Secuencial de factura fuera de rango.');
            }

            $next = $current + 1;
            if ($next > self::MAX_SECUENCIAL) {
                throw new \RuntimeException('Secuencial maximo alcanzado.');
            }

            $row->secuencial_factura = $next;
            $row->save();

            return [
                'secuencial' => $current,
                'secuencial_formateado' => str_pad((string) $current, 9, '0', STR_PAD_LEFT),
            ];
        });
    }
}
