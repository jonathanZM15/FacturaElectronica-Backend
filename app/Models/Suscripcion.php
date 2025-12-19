<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Suscripcion extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'suscripciones';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'emisor_id',
        'plan_id',
        'fecha_inicio',
        'fecha_fin',
        'monto',
        'cantidad_comprobantes',
        'comprobantes_usados',
        'estado_suscripcion',
        'estado_transaccion',
        'forma_pago',
        'comprobante_pago',
        'factura',
        'estado_comision',
        'monto_comision',
        'comprobante_comision',
        'created_by_id',
        'updated_by_id',
        'ip_address',
        'user_agent',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'monto' => 'decimal:2',
        'cantidad_comprobantes' => 'integer',
        'comprobantes_usados' => 'integer',
        'monto_comision' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Estados que bloquean la emisión de comprobantes.
     */
    public const ESTADOS_BLOQUEADOS = [
        'Pendiente',
        'Programado',
        'Caducado',
        'Sin comprobantes',
        'Suspendido',
    ];

    /**
     * Estados manuales (seleccionables por el usuario).
     */
    public const ESTADOS_MANUALES = [
        'Vigente',
        'Suspendido',
    ];

    /**
     * Get the emisor (company) that owns the subscription.
     */
    public function emisor()
    {
        return $this->belongsTo(Company::class, 'emisor_id');
    }

    /**
     * Get the plan associated with the subscription.
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /**
     * Get the user who created this subscription.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Get the user who last updated this subscription.
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /**
     * Calcula los comprobantes restantes.
     */
    public function getComprobantesRestantesAttribute(): int
    {
        return max(0, $this->cantidad_comprobantes - $this->comprobantes_usados);
    }

    /**
     * Calcula los días restantes hasta la fecha de fin.
     */
    public function getDiasRestantesAttribute(): int
    {
        $fechaFin = Carbon::parse($this->fecha_fin);
        $hoy = Carbon::today();
        
        if ($hoy->greaterThan($fechaFin)) {
            return 0;
        }
        
        return $hoy->diffInDays($fechaFin);
    }

    /**
     * Verifica si la suscripción permite emitir comprobantes.
     */
    public function puedeEmitirComprobantes(): bool
    {
        return !in_array($this->estado_suscripcion, self::ESTADOS_BLOQUEADOS) 
               && $this->comprobantes_restantes > 0;
    }

    /**
     * Actualiza el estado automático de la suscripción.
     */
    public function actualizarEstadoAutomatico(bool $persist = true): string
    {
        // Estados protegidos no se recalculan automáticamente en la fuente de datos
        if (in_array($this->estado_suscripcion, ['Suspendido', 'Pendiente'])) {
            return $this->estado_suscripcion;
        }

        $plan = $this->plan;
        $hoy = Carbon::today();
        $fechaInicio = Carbon::parse($this->fecha_inicio);
        $fechaFin = Carbon::parse($this->fecha_fin);
        $nuevoEstado = $this->estado_suscripcion;

        if ($fechaInicio->greaterThan($hoy)) {
            $nuevoEstado = 'Programado';
        } elseif ($hoy->greaterThan($fechaFin)) {
            $nuevoEstado = 'Caducado';
        } elseif ($this->comprobantes_restantes <= 0) {
            $nuevoEstado = 'Sin comprobantes';
        } else {
            $diasRestantes = $this->dias_restantes;
            $comprobantesRestantes = $this->comprobantes_restantes;
            $proximoACaducar = $plan && $diasRestantes <= $plan->dias_minimos;
            $pocosComprobantes = $plan && $comprobantesRestantes <= $plan->comprobantes_minimos;

            if ($proximoACaducar && $pocosComprobantes) {
                $nuevoEstado = 'Proximo a caducar y con pocos comprobantes';
            } elseif ($proximoACaducar) {
                $nuevoEstado = 'Proximo a caducar';
            } elseif ($pocosComprobantes) {
                $nuevoEstado = 'Pocos comprobantes';
            } else {
                $nuevoEstado = 'Vigente';
            }
        }

        // Actualizar el modelo en memoria para reflejar el estado calculado
        $this->estado_suscripcion = $nuevoEstado;

        if ($persist && $this->isDirty('estado_suscripcion')) {
            $this->save();
        }

        return $this->estado_suscripcion;
    }

    /**
     * Scope para suscripciones vigentes.
     */
    public function scopeVigentes($query)
    {
        return $query->where('estado_suscripcion', 'Vigente');
    }

    /**
     * Scope para suscripciones activas (que permiten emitir).
     */
    public function scopeActivas($query)
    {
        return $query->whereNotIn('estado_suscripcion', self::ESTADOS_BLOQUEADOS);
    }

    /**
     * Scope para suscripciones de un emisor específico.
     */
    public function scopeDeEmisor($query, $emisorId)
    {
        return $query->where('emisor_id', $emisorId);
    }

    /**
     * Calcula la fecha de fin según el período del plan.
     */
    public static function calcularFechaFin(Carbon $fechaInicio, string $periodo): Carbon
    {
        return match ($periodo) {
            'Mensual' => $fechaInicio->copy()->addMonth(),
            'Trimestral' => $fechaInicio->copy()->addMonths(3),
            'Semestral' => $fechaInicio->copy()->addMonths(6),
            'Anual' => $fechaInicio->copy()->addYear(),
            'Bianual' => $fechaInicio->copy()->addYears(2),
            'Trianual' => $fechaInicio->copy()->addYears(3),
            default => $fechaInicio->copy()->addMonth(),
        };
    }
}
