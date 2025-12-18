<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoRetencion extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'tipos_retencion';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tipo_retencion',
        'codigo',
        'nombre',
        'porcentaje',
        'created_by_id',
        'updated_by_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'porcentaje' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Tipos de retención disponibles.
     */
    public const TIPOS_RETENCION = [
        'IVA',
        'RENTA',
        'ISD',
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
     * Scope para filtrar por tipo de retención.
     */
    public function scopeOfTipo($query, $tipo)
    {
        return $query->where('tipo_retencion', $tipo);
    }

    /**
     * Scope para filtrar por múltiples tipos de retención.
     */
    public function scopeOfTipos($query, array $tipos)
    {
        return $query->whereIn('tipo_retencion', $tipos);
    }

    /**
     * Scope para búsqueda por nombre (case-insensitive, parcial).
     */
    public function scopeSearchByNombre($query, $nombre)
    {
        return $query->where('nombre', 'LIKE', '%' . $nombre . '%');
    }

    /**
     * Scope para búsqueda por código (case-insensitive, parcial).
     */
    public function scopeSearchByCodigo($query, $codigo)
    {
        return $query->where('codigo', 'LIKE', '%' . $codigo . '%');
    }

    /**
     * Get formatted percentage.
     */
    public function getFormattedPorcentajeAttribute(): string
    {
        return number_format($this->porcentaje, 2) . '%';
    }

    /**
     * Validar que el código solo contenga letras y números.
     */
    public static function isValidCodigo(string $codigo): bool
    {
        return preg_match('/^[a-zA-Z0-9]+$/', $codigo) === 1;
    }
}
