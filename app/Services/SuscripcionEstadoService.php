<?php

namespace App\Services;

use App\Models\Suscripcion;
use App\Models\SuscripcionEstadoAudit;
use App\Models\User;
use App\Enums\UserRole;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SuscripcionEstadoService
{
    /**
     * Todos los estados posibles de suscripción.
     */
    public const ESTADOS = [
        'Vigente',
        'Pendiente',
        'Programado',
        'Suspendido',
        'Proximo a caducar',
        'Pocos comprobantes',
        'Proximo a caducar y con pocos comprobantes',
        'Caducado',
        'Sin comprobantes',
    ];

    /**
     * Estados que son terminales (no permiten más cambios automáticos hacia estados activos).
     */
    public const ESTADOS_TERMINALES = [
        'Caducado',
        'Sin comprobantes',
    ];

    /**
     * Matriz de transiciones permitidas.
     * Formato: [estado_actual => [estado_destino => ['tipo' => 'Manual|Automatico', 'roles' => [...]]]]
     */
    public const TRANSICIONES = [
        'Vigente' => [
            'Programado' => ['tipo' => 'Manual', 'roles' => ['administrador']],
            'Suspendido' => ['tipo' => 'Manual', 'roles' => ['administrador']],
            'Proximo a caducar' => ['tipo' => 'Automatico', 'roles' => []],
            'Pocos comprobantes' => ['tipo' => 'Automatico', 'roles' => []],
            'Proximo a caducar y con pocos comprobantes' => ['tipo' => 'Automatico', 'roles' => []],
            'Caducado' => ['tipo' => 'Automatico', 'roles' => []],
            'Sin comprobantes' => ['tipo' => 'Automatico', 'roles' => []],
        ],
        'Pendiente' => [
            'Vigente' => ['tipo' => 'Manual', 'roles' => ['administrador']],
            'Programado' => ['tipo' => 'Manual', 'roles' => ['administrador', 'distribuidor']],
            'Suspendido' => ['tipo' => 'Manual', 'roles' => ['administrador']],
        ],
        'Programado' => [
            'Vigente' => ['tipo' => 'Automatico', 'roles' => []],
            'Suspendido' => ['tipo' => 'Manual', 'roles' => ['administrador']],
        ],
        'Proximo a caducar' => [
            'Proximo a caducar y con pocos comprobantes' => ['tipo' => 'Automatico', 'roles' => []],
            'Caducado' => ['tipo' => 'Automatico', 'roles' => []],
            'Suspendido' => ['tipo' => 'Manual', 'roles' => ['administrador']],
        ],
        'Pocos comprobantes' => [
            'Proximo a caducar y con pocos comprobantes' => ['tipo' => 'Automatico', 'roles' => []],
            'Sin comprobantes' => ['tipo' => 'Automatico', 'roles' => []],
            'Vigente' => ['tipo' => 'Manual', 'roles' => ['administrador']], // Al aumentar comprobantes
            'Suspendido' => ['tipo' => 'Manual', 'roles' => ['administrador']],
        ],
        'Proximo a caducar y con pocos comprobantes' => [
            'Caducado' => ['tipo' => 'Automatico', 'roles' => []],
            'Sin comprobantes' => ['tipo' => 'Automatico', 'roles' => []],
            'Proximo a caducar' => ['tipo' => 'Manual', 'roles' => ['administrador']], // Al aumentar comprobantes
            'Suspendido' => ['tipo' => 'Manual', 'roles' => ['administrador']],
        ],
        'Sin comprobantes' => [
            'Caducado' => ['tipo' => 'Automatico', 'roles' => []],
            'Vigente' => ['tipo' => 'Manual', 'roles' => ['administrador']], // Al aumentar comprobantes
        ],
        'Suspendido' => [
            'Vigente' => ['tipo' => 'Manual', 'roles' => ['administrador']],
            'Caducado' => ['tipo' => 'Automatico', 'roles' => []],
        ],
        'Caducado' => [
            // Estado terminal - no hay transiciones desde aquí
        ],
    ];

    /**
     * Evalúa y actualiza el estado automático de una suscripción.
     * Retorna true si hubo cambio de estado.
     */
    public function evaluarEstadoAutomatico(Suscripcion $suscripcion): bool
    {
        $estadoActual = $suscripcion->estado_suscripcion;

        // Estados que no deben cambiar automáticamente
        if (in_array($estadoActual, ['Suspendido', 'Pendiente'])) {
            return false;
        }

        $nuevoEstado = $this->calcularEstadoSegunCondiciones($suscripcion);

        if ($nuevoEstado !== $estadoActual) {
            $motivo = $this->obtenerMotivoTransicionAutomatica($estadoActual, $nuevoEstado, $suscripcion);
            
            // Registrar auditoría
            SuscripcionEstadoAudit::registrarTransicionAutomatica(
                $suscripcion->id,
                $estadoActual,
                $nuevoEstado,
                $motivo
            );

            // Actualizar estado
            $suscripcion->estado_suscripcion = $nuevoEstado;
            $suscripcion->saveQuietly(); // Sin disparar eventos para evitar loops

            Log::info('Transición automática de estado', [
                'suscripcion_id' => $suscripcion->id,
                'estado_anterior' => $estadoActual,
                'estado_nuevo' => $nuevoEstado,
                'motivo' => $motivo,
            ]);

            // Si pasó a Caducado o Sin comprobantes, activar suscripción programada
            if (in_array($nuevoEstado, ['Caducado', 'Sin comprobantes'])) {
                $this->activarSuscripcionProgramada($suscripcion->emisor_id);
            }

            return true;
        }

        return false;
    }

    /**
     * Calcula el estado que debería tener la suscripción según las condiciones actuales.
     */
    public function calcularEstadoSegunCondiciones(Suscripcion $suscripcion): string
    {
        $plan = $suscripcion->plan;
        $hoy = Carbon::today();
        $fechaInicio = Carbon::parse($suscripcion->fecha_inicio);
        $fechaFin = Carbon::parse($suscripcion->fecha_fin);

        // 1. Verificar si está programado (fecha inicio futura)
        if ($fechaInicio->greaterThan($hoy)) {
            return 'Programado';
        }

        // 2. Verificar si está caducado (fecha actual > fecha fin)
        if ($hoy->greaterThan($fechaFin)) {
            return 'Caducado';
        }

        // 3. Verificar si no tiene comprobantes
        $comprobantesRestantes = $suscripcion->comprobantes_restantes;
        if ($comprobantesRestantes <= 0) {
            return 'Sin comprobantes';
        }

        // 4. Verificar condiciones de alerta
        $diasRestantes = $suscripcion->dias_restantes;
        $proximoACaducar = $plan && $diasRestantes <= $plan->dias_minimos;
        $pocosComprobantes = $plan && $comprobantesRestantes <= $plan->comprobantes_minimos;

        if ($proximoACaducar && $pocosComprobantes) {
            return 'Proximo a caducar y con pocos comprobantes';
        }

        if ($proximoACaducar) {
            return 'Proximo a caducar';
        }

        if ($pocosComprobantes) {
            return 'Pocos comprobantes';
        }

        // 5. Estado por defecto: Vigente
        return 'Vigente';
    }

    /**
     * Valida si una transición manual es permitida.
     */
    public function validarTransicionManual(
        Suscripcion $suscripcion,
        string $nuevoEstado,
        User $user
    ): array {
        $estadoActual = $suscripcion->estado_suscripcion;
        $userRole = $user->role->value;

        // Verificar si existe la transición
        if (!isset(self::TRANSICIONES[$estadoActual][$nuevoEstado])) {
            return [
                'valido' => false,
                'mensaje' => "No se permite la transición de '{$estadoActual}' a '{$nuevoEstado}'.",
            ];
        }

        $transicion = self::TRANSICIONES[$estadoActual][$nuevoEstado];

        // Verificar que sea una transición manual
        if ($transicion['tipo'] !== 'Manual') {
            return [
                'valido' => false,
                'mensaje' => "La transición a '{$nuevoEstado}' es automática y no puede realizarse manualmente.",
            ];
        }

        // Verificar permisos de rol
        if (!in_array($userRole, $transicion['roles'])) {
            return [
                'valido' => false,
                'mensaje' => "El rol '{$userRole}' no tiene permisos para realizar esta transición.",
            ];
        }

        // Validar condiciones específicas de cada transición
        return $this->validarCondicionesTransicion($suscripcion, $estadoActual, $nuevoEstado);
    }

    /**
     * Valida las condiciones específicas de cada transición.
     */
    private function validarCondicionesTransicion(
        Suscripcion $suscripcion,
        string $estadoActual,
        string $nuevoEstado
    ): array {
        $hoy = Carbon::today();
        $fechaInicio = Carbon::parse($suscripcion->fecha_inicio);
        $fechaFin = Carbon::parse($suscripcion->fecha_fin);
        $fechaMaxima = Carbon::today()->addDays(30);

        // Transición a Programado
        if ($nuevoEstado === 'Programado') {
            // La fecha de inicio debe ser futura y dentro de 30 días
            if (!$fechaInicio->greaterThan($hoy)) {
                return [
                    'valido' => false,
                    'mensaje' => "Para cambiar a 'Programado', la fecha de inicio debe ser una fecha futura.",
                ];
            }
            if ($fechaInicio->greaterThan($fechaMaxima)) {
                return [
                    'valido' => false,
                    'mensaje' => "Para cambiar a 'Programado', la fecha de inicio debe estar dentro de los próximos 30 días.",
                ];
            }
            // No debe tener comprobantes emitidos
            if ($suscripcion->comprobantes_usados > 0) {
                return [
                    'valido' => false,
                    'mensaje' => "No se puede cambiar a 'Programado' si ya existen comprobantes emitidos.",
                ];
            }
        }

        // Transición de Pendiente a Vigente (aprobación)
        if ($estadoActual === 'Pendiente' && $nuevoEstado === 'Vigente') {
            // La fecha de inicio debe ser hoy o pasada, y la fecha fin debe ser futura
            if ($fechaInicio->greaterThan($hoy)) {
                return [
                    'valido' => false,
                    'mensaje' => "Para aprobar como 'Vigente', la fecha de inicio no debe ser futura. Use 'Programado' si la fecha es futura.",
                ];
            }
            if ($hoy->greaterThan($fechaFin)) {
                return [
                    'valido' => false,
                    'mensaje' => "No se puede aprobar como 'Vigente' si la fecha de fin ya pasó.",
                ];
            }
        }

        // Transición de Suspendido a Vigente
        if ($estadoActual === 'Suspendido' && $nuevoEstado === 'Vigente') {
            // Se debe revalidar que las condiciones sean correctas
            if ($hoy->greaterThan($fechaFin)) {
                return [
                    'valido' => false,
                    'mensaje' => "No se puede reactivar como 'Vigente' si la suscripción ya caducó.",
                ];
            }
            if ($suscripcion->comprobantes_restantes <= 0) {
                return [
                    'valido' => false,
                    'mensaje' => "No se puede reactivar como 'Vigente' si no tiene comprobantes disponibles.",
                ];
            }
        }

        // Transiciones al aumentar comprobantes (Pocos comprobantes/Sin comprobantes/Próximo a caducar... -> Vigente)
        if (in_array($estadoActual, ['Pocos comprobantes', 'Sin comprobantes', 'Proximo a caducar y con pocos comprobantes']) 
            && in_array($nuevoEstado, ['Vigente', 'Proximo a caducar'])) {
            
            $plan = $suscripcion->plan;
            $comprobantesRestantes = $suscripcion->comprobantes_restantes;

            if ($nuevoEstado === 'Vigente') {
                // Verificar que ya no esté en condición de pocos comprobantes
                if ($plan && $comprobantesRestantes <= $plan->comprobantes_minimos) {
                    return [
                        'valido' => false,
                        'mensaje' => "Para cambiar a 'Vigente', la cantidad de comprobantes restantes ({$comprobantesRestantes}) debe ser mayor a los comprobantes mínimos del plan ({$plan->comprobantes_minimos}).",
                    ];
                }
            }
        }

        return ['valido' => true, 'mensaje' => ''];
    }

    /**
     * Ejecuta una transición manual de estado.
     */
    public function ejecutarTransicionManual(
        Suscripcion $suscripcion,
        string $nuevoEstado,
        User $user,
        string $motivo = null,
        string $ipAddress = null,
        string $userAgent = null
    ): array {
        // Validar transición
        $validacion = $this->validarTransicionManual($suscripcion, $nuevoEstado, $user);
        
        if (!$validacion['valido']) {
            return $validacion;
        }

        $estadoAnterior = $suscripcion->estado_suscripcion;

        // Registrar auditoría
        SuscripcionEstadoAudit::registrarTransicionManual(
            $suscripcion->id,
            $estadoAnterior,
            $nuevoEstado,
            $user->id,
            $user->role->value,
            $motivo ?? "Cambio manual de '{$estadoAnterior}' a '{$nuevoEstado}'",
            $ipAddress,
            $userAgent
        );

        // Actualizar estado
        $suscripcion->estado_suscripcion = $nuevoEstado;
        $suscripcion->updated_by_id = $user->id;
        $suscripcion->save();

        Log::info('Transición manual de estado', [
            'suscripcion_id' => $suscripcion->id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $nuevoEstado,
            'user_id' => $user->id,
            'user_role' => $user->role->value,
            'motivo' => $motivo,
        ]);

        // Si se activa desde Suspendido, revalidar el estado real
        if ($estadoAnterior === 'Suspendido' && $nuevoEstado === 'Vigente') {
            $this->evaluarEstadoAutomatico($suscripcion);
        }

        return [
            'valido' => true,
            'mensaje' => "Estado actualizado exitosamente de '{$estadoAnterior}' a '{$nuevoEstado}'.",
        ];
    }

    /**
     * Activa la siguiente suscripción programada cuando la actual caduca o se queda sin comprobantes.
     */
    public function activarSuscripcionProgramada(int $emisorId): bool
    {
        // Buscar suscripción programada más antigua
        $suscripcionProgramada = Suscripcion::where('emisor_id', $emisorId)
            ->where('estado_suscripcion', 'Programado')
            ->orderBy('fecha_inicio', 'asc')
            ->first();

        if (!$suscripcionProgramada) {
            return false;
        }

        $estadoAnterior = $suscripcionProgramada->estado_suscripcion;
        $hoy = Carbon::today();

        // Actualizar fecha de inicio a hoy
        $plan = $suscripcionProgramada->plan;
        $suscripcionProgramada->fecha_inicio = $hoy;
        $suscripcionProgramada->fecha_fin = Suscripcion::calcularFechaFin($hoy, $plan->periodo);
        $suscripcionProgramada->estado_suscripcion = 'Vigente';
        $suscripcionProgramada->save();

        // Registrar auditoría
        SuscripcionEstadoAudit::registrarTransicionAutomatica(
            $suscripcionProgramada->id,
            $estadoAnterior,
            'Vigente',
            "Activación automática: la suscripción anterior del emisor pasó a estado terminal. Fecha de inicio actualizada a {$hoy->format('Y-m-d')}."
        );

        Log::info('Suscripción programada activada automáticamente', [
            'suscripcion_id' => $suscripcionProgramada->id,
            'emisor_id' => $emisorId,
            'nueva_fecha_inicio' => $hoy->format('Y-m-d'),
        ]);

        return true;
    }

    /**
     * Obtiene las transiciones manuales disponibles para un estado y rol.
     */
    public function getTransicionesDisponibles(string $estadoActual, string $userRole): array
    {
        $disponibles = [];

        if (!isset(self::TRANSICIONES[$estadoActual])) {
            return $disponibles;
        }

        foreach (self::TRANSICIONES[$estadoActual] as $estadoDestino => $config) {
            if ($config['tipo'] === 'Manual' && in_array($userRole, $config['roles'])) {
                $disponibles[] = $estadoDestino;
            }
        }

        return $disponibles;
    }

    /**
     * Evalúa múltiples suscripciones (para uso en batch, login, etc.).
     */
    public function evaluarMultiplesSuscripciones(array $suscripcionIds): int
    {
        $actualizadas = 0;

        foreach ($suscripcionIds as $id) {
            $suscripcion = Suscripcion::with('plan')->find($id);
            if ($suscripcion && $this->evaluarEstadoAutomatico($suscripcion)) {
                $actualizadas++;
            }
        }

        return $actualizadas;
    }

    /**
     * Evalúa todas las suscripciones de un emisor.
     */
    public function evaluarSuscripcionesEmisor(int $emisorId): int
    {
        $suscripciones = Suscripcion::with('plan')
            ->where('emisor_id', $emisorId)
            ->whereNotIn('estado_suscripcion', ['Suspendido', 'Pendiente'])
            ->get();

        $actualizadas = 0;

        foreach ($suscripciones as $suscripcion) {
            if ($this->evaluarEstadoAutomatico($suscripcion)) {
                $actualizadas++;
            }
        }

        return $actualizadas;
    }

    /**
     * Obtiene el motivo de una transición automática.
     */
    private function obtenerMotivoTransicionAutomatica(
        string $estadoAnterior,
        string $estadoNuevo,
        Suscripcion $suscripcion
    ): string {
        $plan = $suscripcion->plan;
        $diasRestantes = $suscripcion->dias_restantes;
        $comprobantesRestantes = $suscripcion->comprobantes_restantes;

        return match ($estadoNuevo) {
            'Programado' => "Fecha de inicio ({$suscripcion->fecha_inicio->format('Y-m-d')}) es futura.",
            'Caducado' => "Fecha actual superó la fecha fin ({$suscripcion->fecha_fin->format('Y-m-d')}).",
            'Sin comprobantes' => "Comprobantes restantes: 0 de {$suscripcion->cantidad_comprobantes}.",
            'Proximo a caducar' => "Días restantes ({$diasRestantes}) ≤ días mínimos del plan ({$plan?->dias_minimos}).",
            'Pocos comprobantes' => "Comprobantes restantes ({$comprobantesRestantes}) ≤ comprobantes mínimos del plan ({$plan?->comprobantes_minimos}).",
            'Proximo a caducar y con pocos comprobantes' => "Condiciones de 'Próximo a caducar' y 'Pocos comprobantes' se cumplen simultáneamente.",
            'Vigente' => "Condiciones de alerta ya no aplican. Suscripción activa normal.",
            default => "Transición automática de '{$estadoAnterior}' a '{$estadoNuevo}'.",
        };
    }

    /**
     * Obtiene el historial de cambios de estado de una suscripción.
     */
    public function getHistorialEstados(int $suscripcionId): array
    {
        return SuscripcionEstadoAudit::where('suscripcion_id', $suscripcionId)
            ->with('user:id,username,nombres,apellidos')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }
}
