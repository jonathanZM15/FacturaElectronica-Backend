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
use Illuminate\Support\Facades\Mail;
use App\Services\PdfRideService;
use App\Mail\FacturaAutorizadaMail;
use App\Jobs\EnviarComprobanteEmailJob;
use Throwable;

class ConsultarAutorizacionSriJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function tries(): int
    {
        return (int) config('sri.max_authorization_attempts', 10);
    }

    public function __construct(public int $comprobanteId)
    {
    }

    public function handle(SriSoapService $soapService, SriComprobanteLifecycleService $lifecycle): void
    {
        $lock = Cache::lock('sri-comprobante-'.$this->comprobanteId, 180);
        if (!$lock->get()) {
            $this->release(10);
            return;
        }

        try {
            $comprobante = Comprobante::find($this->comprobanteId);

            if (!$comprobante || !$comprobante->clave_acceso) {
                return;
            }

            if (in_array($comprobante->estado_sri, ['AUTORIZADO', 'NO_AUTORIZADO', 'DEVUELTA'], true)) {
                return;
            }

            $autorizacion = $soapService->autorizarComprobante($comprobante->clave_acceso, $comprobante->ambiente);

            $comprobante->forceFill([
                'sri_intentos_autorizacion' => ((int) $comprobante->sri_intentos_autorizacion) + 1,
                'ultima_peticion_autorizacion' => $comprobante->clave_acceso,
                'ultima_respuesta_autorizacion' => json_encode($autorizacion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ])->save();

            if ($autorizacion['success'] ?? false) {
                // P07: Verificar que la autorización traiga evidencia real y no sustituir con dato inventado
                $numAut = trim((string) ($autorizacion['numero_autorizacion'] ?? ''));
                if (empty($numAut)) {
                    throw new \RuntimeException('La respuesta del SRI no contiene un número de autorización válido.');
                }

                if ($comprobante->estado_sri !== 'RECIBIDA') $comprobante = $lifecycle->transition($comprobante, 'RECIBIDA');
                $comprobante = $lifecycle->transition($comprobante, SriEstadoComprobante::AUTORIZADO->value, [
                    'sri_estado_autorizacion' => SriEstadoComprobante::AUTORIZADO->value,
                    'numero_autorizacion' => $numAut,
                    'fecha_autorizacion' => $autorizacion['fecha_autorizacion'] ?? null,
                    'xml_autorizado' => $autorizacion['xml_autorizado'] ?? null,
                    'autorizado_en' => now(),
                    'ultimo_error_sri' => null,
                ], [
                    'etapa' => 'autorizacion',
                    'codigo' => 'AUTORIZADO',
                    'mensaje' => 'Comprobante autorizado por SRI.',
                    'solicitud_payload' => $comprobante->clave_acceso,
                    'respuesta_payload' => json_encode($autorizacion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);

                // P10: Despacho desacoplado y reintentable para generación de RIDE y envío por correo
                EnviarComprobanteEmailJob::dispatch($comprobante->id)->afterCommit();

                return;
            }

            $estado = $autorizacion['estado'] ?? 'DESCONOCIDO';
            if ($estado === 'INCONSISTENTE') {
                $errMsg = $this->flattenErrors($autorizacion['errores'] ?? [], 'Inconsistencia en la respuesta de autorización del SRI.');
                $lifecycle->transition($comprobante, SriEstadoComprobante::ERROR_AUTORIZACION->value, [
                    'ultimo_error_sri' => $errMsg,
                ], [
                    'etapa' => 'autorizacion',
                    'codigo' => 'INCONSISTENTE',
                    'mensaje' => 'Respuesta no correlacionada con la clave de acceso de la factura.',
                    'solicitud_payload' => $comprobante->clave_acceso,
                    'respuesta_payload' => json_encode($autorizacion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'detalles' => $autorizacion['errores'] ?? [],
                ]);
                return;
            }

            if (!in_array($estado, ['NO_AUTORIZADO', 'NO AUTORIZADO', 'RECHAZADO'], true)) {
                if (in_array($estado, ['EN PROCESO', 'EN_PROCESO', 'RECIBIDA', 'DESCONOCIDO'], true) && $this->attempts() < (int) config('sri.max_authorization_attempts', 10)) {
                    $this->release((int) config('sri.retry_after_seconds', 300));
                    return;
                }
                throw new \RuntimeException($autorizacion['error'] ?? 'No se ha confirmado la autorización; no equivale a un rechazo del SRI.');
            }
            if ($comprobante->estado_sri !== 'RECIBIDA') $comprobante = $lifecycle->transition($comprobante, 'RECIBIDA');
            $estadoNormalizado = SriEstadoComprobante::NO_AUTORIZADO->value;

            $lifecycle->transition($comprobante, $estadoNormalizado, [
                'sri_estado_autorizacion' => $estado,
                'ultimo_error_sri' => $this->flattenErrors($autorizacion['errores'] ?? [], $autorizacion['error'] ?? null),
            ], [
                'etapa' => 'autorizacion',
                'codigo' => $estado,
                'mensaje' => 'El comprobante fue devuelto/rechazado por el SRI en fase de autorización.',
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

    /**
     * P08: Registrar agotamiento o muerte del worker con un estado recuperable
     */
    public function failed(Throwable $exception): void
    {
        Log::error('ConsultarAutorizacionSriJob agotó reintentos o falló.', [
            'comprobante_id' => $this->comprobanteId,
            'error' => $exception->getMessage(),
        ]);

        $comprobante = Comprobante::find($this->comprobanteId);
        if ($comprobante && !in_array($comprobante->estado_sri, ['AUTORIZADO', 'NO_AUTORIZADO', 'DEVUELTA'], true)) {
            app(SriComprobanteLifecycleService::class)->transition($comprobante, SriEstadoComprobante::ERROR_AUTORIZACION->value, [
                'ultimo_error_sri' => 'Reintentos de consulta de autorización agotados: ' . $exception->getMessage(),
            ], [
                'etapa' => 'autorizacion',
                'codigo' => 'CONSULTA_AGOTADA',
                'mensaje' => 'Agotados los reintentos automáticos de consulta de autorización SRI.',
                'detalles' => ['error' => $exception->getMessage()],
            ]);
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
