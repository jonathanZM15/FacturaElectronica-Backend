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

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tipo_impuesto',
        'tipo_tarifa',
        'codigo',
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
        'codigo' => 'integer',
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
