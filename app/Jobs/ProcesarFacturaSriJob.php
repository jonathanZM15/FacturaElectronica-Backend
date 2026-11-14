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
        $comprobante = Comprobante::with(['company', 'cliente', 'establecimiento', 'detalles.impuestos.tipoImpuesto', 'impuestos.tipoImpuesto'])->find($this->comprobanteId);
        if (!$comprobante || in_array($comprobante->estado_sri, ['AUTORIZADO', 'NO_AUTORIZADO', 'DEVUELTA'], true)) {
            $this->deleteCertificate();
            return;
        }
        $lock = Cache::lock('sri-comprobante-'.$comprobante->id, 180);
        if (!$lock->get()) return;
        $finished = false;
        try {
            if (in_array($comprobante->estado_sri, ['RECIBIDA', 'ERROR_AUTORIZACION'], true)) {
                ConsultarAutorizacionSriJob::dispatch($comprobante->id);
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
                $comprobante = $lifecycle->transition($comprobante, 'FIRMADO', ['xml_firmado' => $signed, 'firmado_en' => now()], ['etapa' => 'firma', 'mensaje' => 'XML firmado.']);
            }
            $recepcion = $soapService->enviarComprobante($comprobante->xml_firmado, $comprobante->ambiente);
            $comprobante->update(['sri_intentos_envio' => $comprobante->sri_intentos_envio + 1, 'ultima_peticion_recepcion' => $comprobante->xml_firmado, 'ultima_respuesta_recepcion' => json_encode($recepcion, JSON_UNESCAPED_UNICODE)]);
            if (in_array($recepcion['estado'] ?? '', ['SOAP_FAULT', 'ERROR', 'DESCONOCIDO'], true)) {
                $comprobante = $lifecycle->transition($comprobante, 'ERROR_ENVIO', ['ultimo_error_sri' => $recepcion['error'] ?? 'No se obtuvo una respuesta de recepción válida.'], ['etapa' => 'recepcion', 'mensaje' => 'Fallo técnico de comunicación, sin devolución acreditada del SRI.']);
                throw new \RuntimeException($comprobante->ultimo_error_sri);
            }
            if ($comprobante->estado_sri !== 'ENVIADO') $comprobante = $lifecycle->transition($comprobante, 'ENVIADO', ['enviado_en' => now()], ['etapa' => 'recepcion', 'mensaje' => 'Respuesta del servicio de recepción recibida.']);
            if (($recepcion['estado'] ?? '') === 'DEVUELTA') {
                $lifecycle->transition($comprobante, 'DEVUELTA', ['sri_estado_recepcion' => 'DEVUELTA', 'ultimo_error_sri' => $this->flattenErrors($recepcion['errores'] ?? [], $recepcion['error'] ?? null)], ['etapa' => 'recepcion', 'mensaje' => 'Devolución real del servicio SRI.', 'respuesta_payload' => json_encode($recepcion, JSON_UNESCAPED_UNICODE)]);
                $finished = true;
                return;
            }
            if (!($recepcion['success'] ?? false) || ($recepcion['estado'] ?? '') !== 'RECIBIDA') throw new \RuntimeException('Respuesta de recepción no reconocida.');
            $lifecycle->transition($comprobante, 'RECIBIDA', ['sri_estado_recepcion' => 'RECIBIDA', 'recibido_en' => now(), 'ultimo_error_sri' => null], ['etapa' => 'recepcion', 'mensaje' => 'Recepción confirmada por SRI.', 'respuesta_payload' => json_encode($recepcion, JSON_UNESCAPED_UNICODE)]);
            ConsultarAutorizacionSriJob::dispatch($comprobante->id)->delay(now()->addSeconds((int) config('sri.retry_after_seconds')));
            $finished = true;
        } catch (SriFirmaException $e) {
            $lifecycle->transition($comprobante, 'ERROR_FIRMA', ['ultimo_error_sri' => $e->getMessage()], ['etapa' => 'firma', 'mensaje' => 'No se pudo firmar el comprobante.']);
            $finished = true;
        } catch (Throwable $e) {
            if ($comprobante->fresh()->estado_sri !== 'ERROR_ENVIO') {
                $lifecycle->transition($comprobante, 'ERROR_SISTEMA', ['ultimo_error_sri' => $e->getMessage()], ['etapa' => 'orquestacion', 'mensaje' => 'Error técnico del procesamiento.']);
            }
            throw $e;
        } finally {
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
            'error' => $exception->getMessage(),
        ]);
        
        $this->deleteCertificate();
    }

    private function deleteCertificate(): void
    {
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
