<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosTurno extends Model
{
    use HasFactory;

    protected $fillable = [
        'punto_emision_id',
        'usuario_id',
        'fecha_apertura',
        'fecha_cierre',
        'saldo_inicial',
        'saldo_final',
        'estado',
        'observaciones'
    ];

    protected $casts = [
        'fecha_apertura' => 'datetime',
        'fecha_cierre' => 'datetime',
        'saldo_inicial' => 'decimal:2',
        'saldo_final' => 'decimal:2'
    ];

    public function puntoEmision()
    {
        return $this->belongsTo(PuntoEmision::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function comprobantes()
    {
        return $this->hasMany(Comprobante::class, 'pos_turno_id');
    }
}
