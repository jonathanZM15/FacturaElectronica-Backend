<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\TipoMovimientoInventario;

class MotivoMovimiento extends Model
{
    use HasFactory;

    protected $table = 'motivos_movimiento';

    protected $fillable = [
        'emisor_id',
        'codigo',
        'descripcion',
        'tipo_movimiento',
        'activo',
    ];

    protected $casts = [
        'tipo_movimiento' => TipoMovimientoInventario::class,
        'activo' => 'boolean',
    ];

    public function emisor()
    {
        return $this->belongsTo(Company::class, 'emisor_id');
    }
}
