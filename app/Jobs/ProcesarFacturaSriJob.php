<?php

namespace App\Jobs;

use App\Enums\SriEstadoComprobante;
use App\Models\Comprobante;
use App\Services\SriComprobanteLifecycleService;
use App\Services\SriResponseParserService;
use App\Services\SriSoapService;
use App\Services\SriSignatureService;
use App\Services\SriXmlValidatorService;
use App\Services\SriXmlGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class ProcesarFacturaSriJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [300, 900, 1800];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $comprobanteId,
        public string $certificatePath,
        public string $encryptedPassword
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(
        SriXmlGeneratorService $xmlService,
        SriSignatureService $signatureService,
        SriXmlValidatorService $validator,
        SriComprobanteLifecycleService $lifecycle,
        SriSoapService $soapService
    ): void {
        $comprobante = Comprobante::with([
            'company',
            'cliente',
            'establecimiento',
            'detalles.impuestos.tipoImpuesto',
            'impuestos.tipoImpuesto',
        ])->find($this->comprobanteId);

        if (!$comprobante) {
            Log::warning('Comprobante no encontrado para procesamiento SRI.', [
                'comprobante_id' => $this->comprobanteId,
            ]);
            if (Storage::disk(config('sri.certificate_disk'))->exists($this->certificatePath)) {
                Storage::disk(config('sri.certificate_disk'))->delete($this->certificatePath);
            }
            return;
        }

        if (in_array($comprobante->estado_sri, [SriEstadoComprobante::AUTORIZADO->value, SriEstadoComprobante::NO_AUTORIZADO->value], true)) {
            return;
        }

        $lock = Cache::lock('sri-comprobante-'.$comprobante->id, 600);
        if (!$lock->get()) {
            Log::info('Procesamiento SRI omitido por bloqueo de idempotencia.', ['comprobante_id' => $comprobante->id]);
            return;
        }

        try {
            if ($comprobante->estado_sri !== SriEstadoComprobante::PROCESANDO->value) {
                $comprobante = $lifecycle->transition($comprobante, SriEstadoComprobante::PROCESANDO->value, [
                    'ultimo_error_sri' => null,
                ], [
                    'etapa' => 'orquestacion',
                    'mensaje' => 'Inicio de procesamiento SRI.',
                ]);
            }

            $xmlData = $xmlService->generarXmlFactura($comprobante);
            $comprobante->forceFill([
                'clave_acceso' => $xmlData['clave_acceso'],
                'xml_generado' => $xmlData['xml'],
            ])->save();

            $validation = $validator->validateFactura($xmlData['xml']);
            if (!($validation['valid'] ?? false)) {
                $comprobante = $lifecycle->transition($comprobante, SriEstadoComprobante::ERROR_FIRMA->value, [
                    'ultimo_error_sri' => implode(' | ', $validation['errors'] ?? []),
                ], [
                    'etapa' => 'validacion_xml',
                    'mensaje' => 'XML invalido antes de firma.',
                    'detalles' => $validation['errors'] ?? [],
                    'respuesta_payload' => $xmlData['xml'],
                ]);

                return;
            }

            $rutaAbsoluta = Storage::disk(config('sri.certificate_disk'))->path($this->certificatePath);
            $password = Crypt::decryptString($this->encryptedPassword);

            $xmlFirmado = $signatureService->firmarXml(
                $xmlData['xml'],
                $rutaAbsoluta,
                $password
            );

            $comprobante = $lifecycle->transition($comprobante, SriEstadoComprobante::FIRMADO->value, [
                'xml_firmado' => $xmlFirmado,
                'firmado_en' => now(),
            ], [
                'etapa' => 'firma',
                'mensaje' => 'XML firmado correctamente.',
            ]);

            $recepcion = $soapService->enviarComprobante($xmlFirmado);
            $comprobante->forceFill([
                'sri_intentos_envio' => ((int) $comprobante->sri_intentos_envio) + 1,
                'ultima_peticion_recepcion' => $xmlFirmado,
                'ultima_respuesta_recepcion' => json_encode($recepcion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ])->save();

            $comprobante = $lifecycle->transition($comprobante, SriEstadoComprobante::ENVIADO->value, [
                'sri_estado_recepcion' => $recepcion['estado'] ?? 'DESCONOCIDO',
                'enviado_en' => now(),
            ], [
                'etapa' => 'recepcion',
                'codigo' => $recepcion['estado'] ?? null,
                'mensaje' => 'Respuesta de recepcion SRI recibida.',
                'solicitud_payload' => $xmlFirmado,
                'respuesta_payload' => json_encode($recepcion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            if (!($recepcion['success'] ?? false) || ($recepcion['estado'] ?? '') !== SriEstadoComprobante::RECIBIDA->value) {
                $comprobante = $lifecycle->transition($comprobante, SriEstadoComprobante::DEVUELTA->value, [
                    'sri_estado_recepcion' => $recepcion['estado'] ?? 'DESCONOCIDO',
                    'ultimo_error_sri' => $this->flattenErrors($recepcion['errores'] ?? [], $recepcion['error'] ?? null),
                ], [
                    'etapa' => 'recepcion',
                    'codigo' => $recepcion['estado'] ?? null,
                    'mensaje' => 'SRI devolvio el comprobante en recepcion.',
                    'solicitud_payload' => $xmlFirmado,
                    'respuesta_payload' => json_encode($recepcion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'detalles' => $recepcion['errores'] ?? [],
                ]);

                return;
            }

            $lifecycle->transition($comprobante, SriEstadoComprobante::RECIBIDA->value, [
                'sri_estado_recepcion' => SriEstadoComprobante::RECIBIDA->value,
                'recibido_en' => now(),
            ], [
                'etapa' => 'recepcion',
                'codigo' => 'RECIBIDA',
                'mensaje' => 'SRI recibio el comprobante.',
                'solicitud_payload' => $xmlFirmado,
                'respuesta_payload' => json_encode($recepcion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            ConsultarAutorizacionSriJob::dispatch($comprobante->id)->delay(now()->addSeconds((int) config('sri.retry_after_seconds')));
        } catch (Throwable $e) {
            $lifecycle->transition($comprobante, SriEstadoComprobante::ERROR_SISTEMA->value, [
                'ultimo_error_sri' => $e->getMessage(),
            ], [
                'etapa' => 'orquestacion',
                'mensaje' => 'Excepcion al procesar comprobante SRI.',
                'detalles' => ['exception' => get_class($e)],
            ]);

            Log::error('Error al procesar comprobante en SRI.', [
                'comprobante_id' => $comprobante->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if (Storage::disk(config('sri.certificate_disk'))->exists($this->certificatePath)) {
                Storage::disk(config('sri.certificate_disk'))->delete($this->certificatePath);
            }

            optional($lock)->release();
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('ProcesarFacturaSriJob fallo definitivamente.', [
            'comprobante_id' => $this->comprobanteId,
            'error' => $exception->getMessage(),
        ]);
        
        if (Storage::disk(config('sri.certificate_disk'))->exists($this->certificatePath)) {
            Storage::disk(config('sri.certificate_disk'))->delete($this->certificatePath);
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
