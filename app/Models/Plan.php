<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'planes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nombre',
        'cantidad_comprobantes',
        'precio',
        'periodo',
        'observacion',
        'color_fondo',
        'color_texto',
        'estado',
        'comprobantes_minimos',
        'dias_minimos',
        'created_by_id',
        'updated_by_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'cantidad_comprobantes' => 'integer',
        'precio' => 'decimal:2',
        'comprobantes_minimos' => 'integer',
        'dias_minimos' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * Get the user who created this plan.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Get the user who last updated this plan.
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /**
     * Scope to filter active plans.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('estado', 'Activo');
    }

    /**
     * Scope to filter plans by period.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $periodo
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPeriodo($query, $periodo)
    {
        return $query->where('periodo', $periodo);
    }

    /**
     * Get the validation rules for color hexadecimal.
     *
     * @param string $color
     * @return bool
     */
    public static function isValidHexColor($color)
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $color);
    }
}
