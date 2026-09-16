<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lote extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lotes';

    protected $fillable = [
        'producto_id',
        'numero_lote',
        'fecha_fabricacion',
        'fecha_vencimiento',
        'observacion',
        'usuario_registro_id'
    ];

    protected $casts = [
        'fecha_fabricacion' => 'date',
        'fecha_vencimiento' => 'date',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function usuarioRegistro()
    {
        return $this->belongsTo(User::class, 'usuario_registro_id');
    }

    public function existencias()
    {
        return $this->hasMany(ExistenciaLote::class, 'lote_id');
    }
}
