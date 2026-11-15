<?php

namespace App\Jobs;

use App\Models\Comprobante;
use App\Models\ComprobanteSriLog;
use App\Services\PdfRideService;
use App\Services\InvoiceSnapshots;
use App\Mail\FacturaAutorizadaMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EnviarComprobanteEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [60, 300, 900];

    public function __construct(public int $comprobanteId)
    {
    }

    public function handle(PdfRideService $pdfService): void
    {
        $comprobante = Comprobante::with(['cliente', 'company', 'establecimiento'])->find($this->comprobanteId);

        if (!$comprobante || $comprobante->estado_sri !== 'AUTORIZADO') {
            return;
        }

        InvoiceSnapshots::apply($comprobante);
        $email = $comprobante->cliente?->email;

        if (empty($email)) {
            $comprobante->update(['email_estado' => 'NO_APLICA']);
            ComprobanteSriLog::create([
                'comprobante_id' => $comprobante->id,
                'estado' => $comprobante->estado_sri,
                'etapa' => 'entrega_email',
                'codigo' => 'EMAIL_OMITIDO',
                'mensaje' => 'El cliente no posee correo electrónico registrado. Envío de correo omitido.',
            ]);
            return;
        }

        try {
            $pdfContent = $pdfService->generateRide($comprobante);

            Mail::to($email)->send(new FacturaAutorizadaMail($comprobante, $pdfContent));

            $comprobante->update([
                'email_estado' => 'ENVIADO',
                'email_enviado_en' => now(),
                'email_error' => null,
                'email_intentos' => ((int) $comprobante->email_intentos) + 1,
            ]);

            ComprobanteSriLog::create([
                'comprobante_id' => $comprobante->id,
                'estado' => $comprobante->estado_sri,
                'etapa' => 'entrega_email',
                'codigo' => 'EMAIL_ENVIADO',
                'mensaje' => "Documentos RIDE y XML enviados correctamente a {$email}.",
            ]);

            Log::info('Correo de factura autorizada enviado con éxito.', [
                'comprobante_id' => $comprobante->id,
                'email' => $email,
            ]);
        } catch (Throwable $e) {
            $intentosActuales = ((int) $comprobante->email_intentos) + 1;
            $comprobante->update([
                'email_estado' => 'FALLIDO',
                'email_error' => $e->getMessage(),
                'email_intentos' => $intentosActuales,
            ]);

            ComprobanteSriLog::create([
                'comprobante_id' => $comprobante->id,
                'estado' => $comprobante->estado_sri,
                'etapa' => 'entrega_email',
                'codigo' => 'EMAIL_FALLIDO',
                'mensaje' => "Fallo al enviar correo a {$email} (intento {$intentosActuales}): {$e->getMessage()}",
                'detalles' => ['error' => $e->getMessage()],
            ]);

            Log::error('Fallo en la entrega de correo para comprobante.', [
                'comprobante_id' => $comprobante->id,
                'email' => $email,
                'error' => \App\Services\SensitiveDataSanitizer::clean($e->getMessage()),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $comprobante = Comprobante::find($this->comprobanteId);
        if ($comprobante) {
            $comprobante->update([
                'email_estado' => 'FALLIDO',
                'email_error' => 'Intentos máximos de envío de correo agotados: ' . $exception->getMessage(),
            ]);

            ComprobanteSriLog::create([
                'comprobante_id' => $comprobante->id,
                'estado' => $comprobante->estado_sri,
                'etapa' => 'entrega_email',
                'codigo' => 'EMAIL_AGOTADO',
                'mensaje' => 'Se agotaron los reintentos automáticos de envío por correo electrónico.',
                'detalles' => ['error' => $exception->getMessage()],
            ]);
        }
    }
}
