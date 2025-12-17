<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuscripcionEstadoAudit extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'suscripcion_estado_audit';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'suscripcion_id',
        'estado_anterior',
        'estado_nuevo',
        'tipo_transicion',
        'motivo',
        'user_id',
        'user_role',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Get the subscription this audit belongs to.
     */
    public function suscripcion()
    {
        return $this->belongsTo(Suscripcion::class, 'suscripcion_id');
    }

    /**
     * Get the user who made this change (if manual).
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Registra una transición manual de estado.
     */
    public static function registrarTransicionManual(
        int $suscripcionId,
        string $estadoAnterior,
        string $estadoNuevo,
        int $userId,
        string $userRole,
        string $motivo = null,
        string $ipAddress = null,
        string $userAgent = null
    ): self {
        return self::create([
            'suscripcion_id' => $suscripcionId,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'tipo_transicion' => 'Manual',
            'motivo' => $motivo ?? "Cambio manual de estado por usuario",
            'user_id' => $userId,
            'user_role' => $userRole,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }

    /**
     * Registra una transición automática de estado.
     */
    public static function registrarTransicionAutomatica(
        int $suscripcionId,
        string $estadoAnterior,
        string $estadoNuevo,
        string $motivo = null
    ): self {
        return self::create([
            'suscripcion_id' => $suscripcionId,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'tipo_transicion' => 'Automatico',
            'motivo' => $motivo ?? "Transición automática del sistema",
            'user_id' => null,
            'user_role' => null,
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => now(),
        ]);
    }
}
