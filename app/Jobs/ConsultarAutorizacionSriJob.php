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
    public ?int $taskId = null;
    public ?string $taskToken = null;
    public ?int $fiscalRevision = null;
    public ?int $deadlineAt = null;

    public function tries(): int
    {
        return max(1, (int) config('sri.max_authorization_attempts', 300));
    }

    public function backoff(): int
    {
        return max(1, (int) config('sri.retry_after_seconds', 300));
    }

    public function __construct(public int $comprobanteId, ?int $taskId = null, ?string $taskToken = null, ?int $fiscalRevision = null)
    {
        $this->taskId = $taskId;
        $this->taskToken = $taskToken;
        $this->fiscalRevision = $fiscalRevision;
        $this->deadlineAt = time() + max(1, (int) config('sri.authorization_window_seconds', 86400));
    }

    public function handle(SriSoapService $soapService, SriComprobanteLifecycleService $lifecycle): void
    {
        $lock = Cache::lock('sri-comprobante-'.$this->comprobanteId, 180);
        if (!$lock->get()) {
            $this->release(max(180, (int) config('sri.lock_retry_after_seconds', 180)));
            return;
        }

        $registry = app(\App\Services\SriTaskRegistry::class);
        $settled = false;
        $taskFailed = false;
        try {
            if (!$registry->begin($this->taskId, $this->taskToken, $this->fiscalRevision, $this->comprobanteId)) {
                $settled = true;
                return;
            }
            $comprobante = Comprobante::find($this->comprobanteId);

            if (!$comprobante || !$comprobante->clave_acceso) {
                $settled = true;
                return;
            }

            if (in_array($comprobante->estado_sri, ['AUTORIZADO', 'NO_AUTORIZADO', 'DEVUELTA'], true)) {
                $settled = true;
                return;
            }
            if ($this->deadlineAt !== null && time() >= $this->deadlineAt) {
                $lifecycle->transition($comprobante, 'ERROR_AUTORIZACION', [
                    'ultimo_error_sri' => 'Se agotó el plazo de consultas automáticas sin una respuesta final. Consulta nuevamente este comprobante desde Facturas.',
                ], ['etapa' => 'autorizacion', 'codigo' => 'CONSULTA_PLAZO_AGOTADO', 'mensaje' => 'Ciclo de consultas agotado; no se presume rechazo ni se genera otra venta.']);
                $settled = true;
                $taskFailed = true;
                return;
            }

            $autorizacion = $soapService->autorizarComprobante($comprobante->clave_acceso, $comprobante->ambiente);

            if ($autorizacion['success'] ?? false) {
                $errors = app(\App\Services\SriAuthorizationEvidence::class)->errors(
                    $autorizacion, $comprobante->clave_acceso, $comprobante->ambiente, (string) $comprobante->xml_firmado
                );
                if ($errors) {
                    $autorizacion['success'] = false;
                    $autorizacion['estado'] = 'INCONSISTENTE';
                    $autorizacion['errores'] = array_map(fn ($message) => ['identificador' => 'ERR_EVIDENCIA', 'mensaje' => $message], $errors);
                }
            }

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
                    'fecha_autorizacion' => \Illuminate\Support\Carbon::parse($autorizacion['fecha_autorizacion'])->timezone(config('app.timezone')),
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

                $settled = true;
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
                $settled = true;
                $taskFailed = true;
                return;
            }

            if (!in_array($estado, ['NO_AUTORIZADO', 'NO AUTORIZADO', 'RECHAZADO'], true)) {
                if (in_array($estado, ['EN PROCESO', 'EN_PROCESO', 'RECIBIDA', 'DESCONOCIDO'], true) && $this->attempts() < $this->tries()) {
                    $registry->finish($this->taskId, $this->taskToken, 'pending');
                    $delay = max(1, (int) config('sri.retry_after_seconds', 300));
                    $this->release($this->deadlineAt ? max(1, min($delay, $this->deadlineAt - time())) : $delay);
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
            $settled = true;
        } catch (Throwable $e) {
            Log::error('ConsultarAutorizacionSriJob fallo.', [
                'comprobante_id' => $this->comprobanteId,
                'error' => \App\Services\SensitiveDataSanitizer::clean($e->getMessage()),
            ]);

            if (($comprobante ?? null) && $comprobante->fresh()?->estado_sri !== 'AUTORIZADO') {
                $lifecycle->transition($comprobante, SriEstadoComprobante::ERROR_AUTORIZACION->value, [
                    'ultimo_error_sri' => $e->getMessage(),
                ], [
                    'etapa' => 'autorizacion',
                    'mensaje' => 'Excepcion al consultar autorizacion SRI.',
                    'detalles' => ['exception' => get_class($e)],
                ]);
            } elseif ($comprobante ?? null) {
                $comprobante->update(['email_estado' => 'FALLIDO', 'email_error' => 'La autorización se conservó; no se pudo programar la entrega. Reintenta el correo desde Facturas.']);
            }

            $registry->finish($this->taskId, $this->taskToken, 'pending', $e->getMessage());
            throw $e;
        } finally {
            if ($settled) $registry->finish($this->taskId, $this->taskToken, $taskFailed ? 'failed' : 'done');
            optional($lock)->release();
        }
    }

    /**
     * P08: Registrar agotamiento o muerte del worker con un estado recuperable
     */
    public function failed(Throwable $exception): void
    {
        app(\App\Services\SriTaskRegistry::class)->finish($this->taskId, $this->taskToken, 'failed', $exception->getMessage());
        Log::error('ConsultarAutorizacionSriJob agotó reintentos o falló.', [
            'comprobante_id' => $this->comprobanteId,
            'error' => \App\Services\SensitiveDataSanitizer::clean($exception->getMessage()),
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
