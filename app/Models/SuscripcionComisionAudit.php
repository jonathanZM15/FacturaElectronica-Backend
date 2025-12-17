<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuscripcionComisionAudit extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'suscripcion_comision_audit';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'suscripcion_id',
        'user_id',
        'user_role',
        'campo',
        'valor_anterior',
        'valor_nuevo',
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
     * Get the suscripcion associated with the audit.
     */
    public function suscripcion()
    {
        return $this->belongsTo(Suscripcion::class, 'suscripcion_id');
    }

    /**
     * Get the user who made the change.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Create an audit record for a commission field change.
     */
    public static function registrarCambio(
        int $suscripcionId,
        int $userId,
        string $userRole,
        string $campo,
        $valorAnterior,
        $valorNuevo,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        return self::create([
            'suscripcion_id' => $suscripcionId,
            'user_id' => $userId,
            'user_role' => $userRole,
            'campo' => $campo,
            'valor_anterior' => is_null($valorAnterior) ? null : (string)$valorAnterior,
            'valor_nuevo' => is_null($valorNuevo) ? null : (string)$valorNuevo,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }
}
