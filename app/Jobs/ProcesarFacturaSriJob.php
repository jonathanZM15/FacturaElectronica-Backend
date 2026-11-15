<?php

namespace App\Jobs;

use App\Enums\SriEstadoComprobante;
use App\Exceptions\SriFirmaException;
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
    public int $timeout = 60;
    public array $backoff = [300, 900, 1800];
    public ?int $taskId = null;
    public ?string $taskToken = null;
    public ?int $fiscalRevision = null;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $comprobanteId,
        public string $certificatePath,
        public string $encryptedPassword,
        ?int $taskId = null,
        ?string $taskToken = null,
        ?int $fiscalRevision = null
    ) {
        $this->taskId = $taskId;
        $this->taskToken = $taskToken;
        $this->fiscalRevision = $fiscalRevision;
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
        $lock = Cache::lock('sri-comprobante-'.$this->comprobanteId, 180);
        if (!$lock->get()) {
            $this->release(max(180, (int) config('sri.lock_retry_after_seconds', 180)));
            return;
        }

        $finished = false;
        $failed = false;
        $registry = app(\App\Services\SriTaskRegistry::class);
        try {
            if (!$registry->begin($this->taskId, $this->taskToken, $this->fiscalRevision, $this->comprobanteId)) {
                $finished = true;
                return;
            }
            $comprobante = Comprobante::with(['company', 'cliente', 'establecimiento', 'detalles.impuestos.tipoImpuesto', 'impuestos.tipoImpuesto'])->find($this->comprobanteId);
            if (!$comprobante || in_array($comprobante->estado_sri, ['AUTORIZADO', 'NO_AUTORIZADO', 'DEVUELTA'], true)) {
                $finished = true;
                return;
            }
            if (in_array($comprobante->estado_sri, ['RECIBIDA', 'ERROR_AUTORIZACION'], true)) {
                app(\App\Services\InvoiceProcessingService::class)->dispatchAuthorization($comprobante);
                $finished = true;
                return;
            }
            if (!$comprobante->xml_firmado) {
                if ($comprobante->estado_sri !== 'PROCESANDO') {
                    $comprobante = $lifecycle->transition($comprobante, 'PROCESANDO', [], ['etapa' => 'orquestacion', 'mensaje' => 'Inicio del procesamiento del mismo comprobante.']);
                }
                if (!$comprobante->xml_generado) {
                    if ($comprobante->tipo_comprobante !== 'FACTURA') throw new \RuntimeException('Tipo de comprobante fuera del alcance habilitado de V1.');
                    $generated = $xmlService->generarXmlFactura($comprobante);
                    $comprobante->update(['clave_acceso' => $generated['clave_acceso'], 'xml_generado' => $generated['xml']]);
                }
                $validation = $validator->validateFactura($comprobante->xml_generado);
                if (!$validation['valid']) {
                    $lifecycle->transition($comprobante, 'ERROR_FIRMA', ['ultimo_error_sri' => implode(' | ', $validation['errors'])], ['etapa' => 'validacion_xml', 'mensaje' => 'XML rechazado por el esquema XSD.', 'detalles' => $validation['errors']]);
                    $finished = true;
                    return;
                }
                $signed = $signatureService->firmarXml($comprobante->xml_generado, Storage::disk(config('sri.certificate_disk', 'local'))->path($this->certificatePath), Crypt::decryptString($this->encryptedPassword));
                $verification = $signatureService->verificarXmlFirmado($signed);
                if (!$verification['valida']) throw new SriFirmaException($verification['error']);
                $comprobante = $lifecycle->transition($comprobante, 'FIRMADO', ['xml_firmado' => $signed, 'firmado_en' => now()], ['etapa' => 'firma', 'mensaje' => 'XML firmado.']);
            }
            $verification = $signatureService->verificarXmlFirmado($comprobante->xml_firmado);
            if (!$verification['valida']) throw new \RuntimeException('El XML firmado conservado no supera la verificación de integridad. Revisa la evidencia antes de reenviarlo.');
            $recepcion = $soapService->enviarComprobante($comprobante->xml_firmado, $comprobante->ambiente);
            $comprobante->update(['sri_intentos_envio' => $comprobante->sri_intentos_envio + 1, 'ultima_peticion_recepcion' => $comprobante->xml_firmado, 'ultima_respuesta_recepcion' => json_encode($recepcion, JSON_UNESCAPED_UNICODE)]);
            if (in_array($recepcion['estado'] ?? '', ['SOAP_FAULT', 'ERROR', 'DESCONOCIDO'], true)) {
                $comprobante = $lifecycle->transition($comprobante, 'ERROR_ENVIO', ['ultimo_error_sri' => $recepcion['error'] ?? 'No se obtuvo una respuesta de recepción válida.'], ['etapa' => 'recepcion', 'mensaje' => 'Fallo técnico de comunicación, sin devolución acreditada del SRI.']);
                throw new \RuntimeException($comprobante->ultimo_error_sri);
            }
            if ($comprobante->estado_sri !== 'ENVIADO') $comprobante = $lifecycle->transition($comprobante, 'ENVIADO', ['enviado_en' => now()], ['etapa' => 'recepcion', 'mensaje' => 'Respuesta del servicio de recepción recibida.']);
            if (($recepcion['estado'] ?? '') === 'DEVUELTA') {
                // P05: Si el SRI devuelve código 70 (en procesamiento) o 43 (clave ya registrada),
                // no marcar como fallo terminal DEVUELTA, sino programar consulta de autorización con la misma clave.
                if (!empty($recepcion['es_clave_en_proceso']) || !empty($recepcion['es_clave_registrada'])) {
                    $motivo = !empty($recepcion['es_clave_en_proceso'])
                        ? 'Clave de acceso en procesamiento en el SRI (código 70). Se programa consulta de autorización sin anular comprobante.'
                        : 'Clave de acceso ya registrada en el SRI (código 43). Se programa consulta de autorización para reconciliación.';

                    $lifecycle->transition($comprobante, 'RECIBIDA', [
                        'sri_estado_recepcion' => !empty($recepcion['es_clave_en_proceso']) ? 'EN_PROCESO' : 'REGISTRADA',
                        'recibido_en' => now(),
                        'ultimo_error_sri' => null,
                    ], [
                        'etapa' => 'recepcion',
                        'codigo' => !empty($recepcion['es_clave_en_proceso']) ? '70' : '43',
                        'mensaje' => $motivo,
                        'respuesta_payload' => json_encode($recepcion, JSON_UNESCAPED_UNICODE),
                    ]);

                    app(\App\Services\InvoiceProcessingService::class)->dispatchAuthorization($comprobante->fresh(), max(1, (int) config('sri.retry_after_seconds', 300)));
                    $finished = true;
                    return;
                }

                $lifecycle->transition($comprobante, 'DEVUELTA', ['sri_estado_recepcion' => 'DEVUELTA', 'ultimo_error_sri' => $this->flattenErrors($recepcion['errores'] ?? [], $recepcion['error'] ?? null)], ['etapa' => 'recepcion', 'mensaje' => 'Devolución real del servicio SRI.', 'respuesta_payload' => json_encode($recepcion, JSON_UNESCAPED_UNICODE)]);
                $finished = true;
                return;
            }
            if (!($recepcion['success'] ?? false) || ($recepcion['estado'] ?? '') !== 'RECIBIDA') throw new \RuntimeException('Respuesta de recepción no reconocida.');
            $lifecycle->transition($comprobante, 'RECIBIDA', ['sri_estado_recepcion' => 'RECIBIDA', 'recibido_en' => now(), 'ultimo_error_sri' => null], ['etapa' => 'recepcion', 'mensaje' => 'Recepción confirmada por SRI.', 'respuesta_payload' => json_encode($recepcion, JSON_UNESCAPED_UNICODE)]);
            app(\App\Services\InvoiceProcessingService::class)->dispatchAuthorization($comprobante->fresh(), max(1, (int) config('sri.retry_after_seconds', 300)));
            $finished = true;
        } catch (SriFirmaException $e) {
            $lifecycle->transition($comprobante, 'ERROR_FIRMA', ['ultimo_error_sri' => $e->getMessage()], ['etapa' => 'firma', 'mensaje' => 'No se pudo firmar el comprobante.']);
            $finished = true;
            $failed = true;
        } catch (Throwable $e) {
            if (($comprobante ?? null) && $comprobante->fresh()?->estado_sri !== 'ERROR_ENVIO') {
                $lifecycle->transition($comprobante, 'ERROR_SISTEMA', ['ultimo_error_sri' => $e->getMessage()], ['etapa' => 'orquestacion', 'mensaje' => 'Error técnico del procesamiento.']);
            }
            $registry->finish($this->taskId, $this->taskToken, 'pending', $e->getMessage());
            throw $e;
        } finally {
            if ($finished) $registry->finish($this->taskId, $this->taskToken, $failed ? 'failed' : 'done');
            if ($finished || $this->attempts() >= $this->tries) $this->deleteCertificate();
            $lock->release();
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('ProcesarFacturaSriJob fallo definitivamente.', [
            'comprobante_id' => $this->comprobanteId,
            'error' => \App\Services\SensitiveDataSanitizer::clean($exception->getMessage()),
        ]);
        
        $comprobante = Comprobante::find($this->comprobanteId);
        if ($comprobante && !in_array($comprobante->estado_sri, ['AUTORIZADO', 'NO_AUTORIZADO', 'DEVUELTA'], true)) {
            app(SriComprobanteLifecycleService::class)->transition($comprobante, 'ERROR_SISTEMA', [
                'ultimo_error_sri' => 'El procesamiento agotó sus reintentos máximos o el worker terminó inesperadamente: ' . $exception->getMessage(),
            ], [
                'etapa' => 'orquestacion',
                'codigo' => 'PROCESO_AGOTADO',
                'mensaje' => 'Agotados los reintentos automáticos de procesamiento y firma SRI.',
                'detalles' => ['error' => $exception->getMessage()],
            ]);
        }

        app(\App\Services\SriTaskRegistry::class)->finish($this->taskId, $this->taskToken, 'failed', $exception->getMessage());
    }

    private function deleteCertificate(): void
    {
        app(\App\Services\SriTaskRegistry::class)->deleteCertificateIfUnused($this->certificatePath);
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
