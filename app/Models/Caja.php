<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Caja extends Model
{
    use HasFactory;

    protected $fillable = [
        'establecimiento_id',
        'nombre',
        'activa'
    ];

    protected $casts = [
        'activa' => 'boolean'
    ];

    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function turnos()
    {
        return $this->hasMany(PosTurno::class);
    }
}
