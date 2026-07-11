<?php

namespace App\Jobs;

use App\Enums\SriEstadoComprobante;
use App\Models\Comprobante;
use App\Services\SriComprobanteLifecycleService;
use App\Services\SriSoapService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConsultarAutorizacionSriJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;
    public int $timeout = 90;

    public function __construct(public int $comprobanteId)
    {
    }

    public function handle(SriSoapService $soapService, SriComprobanteLifecycleService $lifecycle): void
    {
        $comprobante = Comprobante::find($this->comprobanteId);

        if (!$comprobante || !$comprobante->clave_acceso) {
            return;
        }

        $lock = Cache::lock('sri-autorizacion-'.$comprobante->id, 300);
        if (!$lock->get()) {
            return;
        }

        try {
            if ($comprobante->estado_sri === SriEstadoComprobante::AUTORIZADO->value) {
                return;
            }

            $autorizacion = $soapService->autorizarComprobante($comprobante->clave_acceso);

            $comprobante->forceFill([
                'sri_intentos_autorizacion' => ((int) $comprobante->sri_intentos_autorizacion) + 1,
                'ultima_peticion_autorizacion' => $comprobante->clave_acceso,
                'ultima_respuesta_autorizacion' => json_encode($autorizacion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ])->save();

            if ($autorizacion['success'] ?? false) {
                $lifecycle->transition($comprobante, SriEstadoComprobante::AUTORIZADO->value, [
                    'sri_estado_autorizacion' => SriEstadoComprobante::AUTORIZADO->value,
                    'numero_autorizacion' => $autorizacion['numero_autorizacion'] ?? $comprobante->clave_acceso,
                    'fecha_autorizacion' => $autorizacion['fecha_autorizacion'] ?: null,
                    'xml_autorizado' => $autorizacion['xml_autorizado'] ?? null,
                    'autorizado_en' => now(),
                ], [
                    'etapa' => 'autorizacion',
                    'codigo' => 'AUTORIZADO',
                    'mensaje' => 'Comprobante autorizado por SRI.',
                    'solicitud_payload' => $comprobante->clave_acceso,
                    'respuesta_payload' => json_encode($autorizacion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);

                return;
            }

            $estado = $autorizacion['estado'] ?? 'DESCONOCIDO';
            $estadoNormalizado = SriEstadoComprobante::NO_AUTORIZADO->value;

            if (($autorizacion['errores'] ?? []) === [] && in_array($estado, ['EN PROCESO', 'RECIBIDA', 'DESCONOCIDO'], true)) {
                $this->release((int) config('sri.retry_after_seconds'));
                return;
            }

            $lifecycle->transition($comprobante, $estadoNormalizado, [
                'sri_estado_autorizacion' => $estado,
                'ultimo_error_sri' => $this->flattenErrors($autorizacion['errores'] ?? [], $autorizacion['error'] ?? null),
            ], [
                'etapa' => 'autorizacion',
                'codigo' => $estado,
                'mensaje' => 'El comprobante aun no se autoriza o fue rechazado.',
                'solicitud_payload' => $comprobante->clave_acceso,
                'respuesta_payload' => json_encode($autorizacion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'detalles' => $autorizacion['errores'] ?? [],
            ]);
        } catch (Throwable $e) {
            Log::error('ConsultarAutorizacionSriJob fallo.', [
                'comprobante_id' => $this->comprobanteId,
                'error' => $e->getMessage(),
            ]);

            if ($comprobante ?? null) {
                $lifecycle->transition($comprobante, SriEstadoComprobante::ERROR_AUTORIZACION->value, [
                    'ultimo_error_sri' => $e->getMessage(),
                ], [
                    'etapa' => 'autorizacion',
                    'mensaje' => 'Excepcion al consultar autorizacion SRI.',
                    'detalles' => ['exception' => get_class($e)],
                ]);
            }

            throw $e;
        } finally {
            optional($lock)->release();
        }
    }

    private function flattenErrors(array $errores, ?string $fallback = null): string
    {
        $messages = [];

        foreach ($errores as $error) {
            if (is_array($error)) {
                $messages[] = trim(implode(' ', array_filter([
                    $error['identificador'] ?? null,
                    $error['mensaje'] ?? null,
                    $error['informacion_adicional'] ?? null,
                ])));
                continue;
            }

            if (is_string($error)) {
                $messages[] = $error;
            }
        }

        if ($fallback) {
            $messages[] = $fallback;
        }

        return trim(implode(' | ', array_filter($messages)));
    }
}