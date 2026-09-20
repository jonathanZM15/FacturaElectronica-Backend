<?php

namespace App\Models;

use App\Enums\TipoMovimientoInventario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kardex extends Model
{
    use HasFactory;

    protected $table = 'kardex';
    public $timestamps = false; // El kardex es inmutable, gestionamos la fecha de creación en fecha_hora

    protected $fillable = [
        'fecha_hora',
        'producto_id',
        'bodega_id',
        'tipo_movimiento',
        'documento_origen_tipo',
        'documento_origen_id',
        'numero_documento',
        'entrada',
        'salida',
        'saldo',
        'usuario_id'
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'tipo_movimiento' => TipoMovimientoInventario::class,
        'entrada' => 'decimal:6',
        'salida' => 'decimal:6',
        'saldo' => 'decimal:6',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function bodega()
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
    
    public function documentoOrigen()
    {
        return $this->morphTo(__FUNCTION__, 'documento_origen_tipo', 'documento_origen_id');
    }

    public function registroOperativo()
    {
        return $this->belongsTo(RegistroOperativoMovimiento::class, 'documento_origen_id');
    }

    public function setRelation($relation, $value)
    {
        // En eager loading (with), evita asignar RegistroOperativo a registros con otro documento_origen_tipo
        if ($relation === 'registroOperativo' && $this->documento_origen_tipo !== 'RegistroOperativo') {
            return parent::setRelation($relation, null);
        }

        return parent::setRelation($relation, $value);
    }
}
