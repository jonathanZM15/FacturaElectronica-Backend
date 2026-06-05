<?php

namespace App\Jobs;

use App\Models\Comprobante;
use App\Services\SriSoapService;
use App\Services\SriSignatureService;
use App\Services\SriXmlGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
        public string $pathFirma,
        public string $password
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(
        SriXmlGeneratorService $xmlService,
        SriSignatureService $signatureService,
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
            if (Storage::disk('local')->exists($this->pathFirma)) {
                Storage::disk('local')->delete($this->pathFirma);
            }
            return;
        }

        try {
            $xmlData = $xmlService->generarXmlFactura($comprobante);
            $comprobante->clave_acceso = $xmlData['clave_acceso'];
            $comprobante->save();

            $rutaAbsoluta = Storage::disk('local')->path($this->pathFirma);

            $xmlFirmado = $signatureService->firmarXml(
                $xmlData['xml'],
                $rutaAbsoluta,
                $this->password
            );

            $comprobante->estado_sri = 'FIRMADA';
            $comprobante->save();

            $recepcion = $soapService->enviarComprobante($xmlFirmado);
            if (!($recepcion['success'] ?? false) || ($recepcion['estado'] ?? '') !== 'RECIBIDA') {
                $comprobante->estado_sri = 'RECHAZADA';
                $comprobante->save();
                return;
            }

            $comprobante->estado_sri = 'ENVIADA';
            $comprobante->save();

            $autorizacion = $soapService->autorizarComprobante($comprobante->clave_acceso);
            if ($autorizacion['success'] ?? false) {
                $comprobante->estado_sri = 'AUTORIZADA';
                $comprobante->numero_autorizacion = $autorizacion['numero_autorizacion'] ?? $comprobante->clave_acceso;
                $comprobante->fecha_autorizacion = $autorizacion['fecha_autorizacion'] ?? null;
                $comprobante->xml_autorizado = $autorizacion['xml_autorizado'] ?? null;
                $comprobante->save();
                return;
            }

            $comprobante->estado_sri = $autorizacion['estado'] ?? 'DEVUELTA';
            $comprobante->save();
        } catch (Throwable $e) {
            $comprobante->estado_sri = 'ERROR_SISTEMA';
            $comprobante->save();

            Log::error('Error al procesar comprobante en SRI.', [
                'comprobante_id' => $comprobante->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if (Storage::disk('local')->exists($this->pathFirma)) {
                Storage::disk('local')->delete($this->pathFirma);
            }
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
        
        if (Storage::disk('local')->exists($this->pathFirma)) {
            Storage::disk('local')->delete($this->pathFirma);
        }
    }
}
