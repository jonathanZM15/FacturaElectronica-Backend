<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoImpuesto extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'tipos_impuesto';

    protected $appends = ['codigo'];

    // Compatibility for existing UI consumers; the database uses both SRI codes.
    public function getCodigoAttribute(): ?int
    {
        return $this->codigo_porcentaje;
    }


    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tipo_impuesto',
        'tipo_tarifa',
        'codigo_impuesto',
        'codigo_porcentaje',
        'nombre',
        'valor_tarifa',
        'estado',
        'created_by_id',
        'updated_by_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'codigo_impuesto' => 'integer',
        'codigo_porcentaje' => 'integer',
        'valor_tarifa' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Tipos de impuesto disponibles.
     */
    public const TIPOS_IMPUESTO = [
        'IVA',
        'ICE',
        'IRBPNR',
    ];

    /**
     * Tipos de tarifa disponibles.
     */
    public const TIPOS_TARIFA = [
        'Porcentaje',
        'Importe fijo por unidad',
    ];

    /**
     * Estados disponibles.
     */
    public const ESTADOS = [
        'Activo',
        'Desactivado',
    ];

    /**
     * Reglas de tipo de tarifa según el tipo de impuesto:
     * - IVA: solo Porcentaje
     * - ICE: Porcentaje o Importe fijo por unidad
     * - IRBPNR: solo Importe fijo por unidad
     */
    public const TARIFA_POR_TIPO = [
        'IVA' => ['Porcentaje'],
        'ICE' => ['Porcentaje', 'Importe fijo por unidad'],
        'IRBPNR' => ['Importe fijo por unidad'],
    ];

    /**
     * Catálogo oficial de combinaciones vigentes de código de porcentaje y tarifa de IVA
     * según la Ficha Técnica de Comprobantes Electrónicos del SRI (Tabla 17).
     */
    public const IVA_COMBINACIONES_SRI = [
        0  => ['tarifa' => 0.0,  'descripcion' => '0%'],
        2  => ['tarifa' => 12.0, 'descripcion' => '12%'],
        3  => ['tarifa' => 14.0, 'descripcion' => '14%'],
        4  => ['tarifa' => 15.0, 'descripcion' => '15%'],
        5  => ['tarifa' => 5.0,  'descripcion' => '5%'],
        6  => ['tarifa' => 0.0,  'descripcion' => 'No Objeto de Impuesto'],
        7  => ['tarifa' => 0.0,  'descripcion' => 'Exento de IVA'],
        8  => ['tarifa' => 8.0,  'descripcion' => 'IVA diferenciado'],
        10 => ['tarifa' => 13.0, 'descripcion' => '13%'],
    ];

    /**
     * Valida si una combinación de código de porcentaje y tarifa corresponde al estándar oficial del SRI para IVA.
     */
    public static function esCombinacionIvaValida(int $codigoPorcentaje, float $tarifa): bool
    {
        if (!array_key_exists($codigoPorcentaje, self::IVA_COMBINACIONES_SRI)) {
            return false;
        }

        $tarifaEsperada = (float) self::IVA_COMBINACIONES_SRI[$codigoPorcentaje]['tarifa'];
        return abs($tarifa - $tarifaEsperada) < 0.0001;
    }

    /**
     * Retorna la tarifa oficial esperada por el SRI para un código de porcentaje de IVA dado.
     */
    public static function getTarifaIvaOficial(int $codigoPorcentaje): ?float
    {
        return isset(self::IVA_COMBINACIONES_SRI[$codigoPorcentaje])
            ? (float) self::IVA_COMBINACIONES_SRI[$codigoPorcentaje]['tarifa']
            : null;
    }

    /**
     * Get the user who created this record.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Get the user who last updated this record.
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /**
     * Get the products associated with this tax type.
     * (Relación preparada para cuando exista el modelo Producto)
     */
    public function productos()
    {
        return $this->hasMany('App\Models\Producto', 'tipo_impuesto_id');
    }

    /**
     * Check if this tax type has associated products.
     */
    public function tieneProductosAsociados(): bool
    {
        // Verificar si existe la tabla productos y si hay registros asociados
        if (\Illuminate\Support\Facades\Schema::hasTable('productos')) {
            return \Illuminate\Support\Facades\DB::table('productos')
                ->where('tipo_impuesto_id', $this->id)
                ->exists();
        }
        return false;
    }

    /**
     * Get count of associated products.
     */
    public function contarProductosAsociados(): int
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('productos')) {
            return \Illuminate\Support\Facades\DB::table('productos')
                ->where('tipo_impuesto_id', $this->id)
                ->count();
        }
        return 0;
    }

    /**
     * Scope for active tax types.
     */
    public function scopeActivos($query)
    {
        return $query->where('estado', 'Activo');
    }

    /**
     * Scope for filtering by tax type.
     */
    public function scopePorTipoImpuesto($query, $tipo)
    {
        return $query->where('tipo_impuesto', $tipo);
    }

    /**
     * Get allowed tariff types for this tax type.
     */
    public function getTarifasPermitidas(): array
    {
        return self::TARIFA_POR_TIPO[$this->tipo_impuesto] ?? [];
    }

    /**
     * Check if the tariff type can be changed.
     */
    public function puedeCambiarTarifa(): bool
    {
        return count($this->getTarifasPermitidas()) > 1;
    }
}
