<?php

namespace App\Models;

use App\Exceptions\InvalidWarehouseOperationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistroOperativoMovimiento extends Model
{
    protected $table = 'registros_operativos_movimiento';
    protected $guarded = [];

    protected $casts = [
        'estado' => \App\Enums\EstadoRegistroOperativo::class,
        'tipo_movimiento' => \App\Enums\TipoMovimientoInventario::class,
        'estado_operativo_transferencia' => \App\Enums\EstadoOperativoTransferencia::class,
        'estado_operativo_recepcion' => \App\Enums\EstadoOperativoRecepcion::class,
        'fecha' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($movimiento) {
            // Regla de Negocio: Bloquear eliminación de despachos si ya tienen recepción (así sea borrador)
            if (self::where('movimiento_origen_id', $movimiento->id)->exists()) {
                throw new InvalidWarehouseOperationException("No se puede eliminar el documento de tránsito porque ya tiene un documento de recepción asociado (MOV-08).");
            }
        });
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(RegistroOperativoDetalle::class, 'registro_operativo_id');
    }

    public function bodegaOrigen()
    {
        return $this->belongsTo(Bodega::class, 'bodega_origen_id');
    }

    public function bodegaDestino()
    {
        return $this->belongsTo(Bodega::class, 'bodega_destino_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
