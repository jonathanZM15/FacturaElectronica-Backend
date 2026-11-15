<?php

namespace App\Console\Commands;

use App\Models\Comprobante;
use App\Jobs\ConsultarAutorizacionSriJob;
use App\Services\InvoiceProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ReconciliarComprobantesSriCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sri:reconciliar {--minutes=5 : Minutos de inactividad antes de reconciliar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'P09: Reconciliar comprobantes detenidos sin trabajo en cola o en estados no terminales';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $minutes = max(0, (int) $this->option('minutes'));
        $threshold = $minutes > 0 ? now()->subMinutes($minutes) : now()->addSecond();

        $comprobantes = Comprobante::whereIn('estado_sri', [
            'BORRADOR',
            'PROCESANDO',
            'FIRMADO',
            'ENVIADO',
            'RECIBIDA',
            'ERROR_ENVIO',
            'ERROR_FIRMA',
            'ERROR_AUTORIZACION',
            'ERROR_SISTEMA',
        ])
        ->where('updated_at', '<=', $threshold)
        ->get();

        $this->info("Reconciliador SRI: Encontrados {$comprobantes->count()} comprobantes candidatos con más de {$minutes} minutos sin actividad.");

        $reconciliados = 0;
        $processingService = app(InvoiceProcessingService::class);

        foreach ($comprobantes as $comprobante) {
            $lock = Cache::lock('sri-comprobante-' . $comprobante->id, 60);

            if (!$lock->get()) {
                $this->line("Comprobante {$comprobante->id}: Omitido (bloqueo activo en procesamiento).");
                continue;
            }

            try {
                $comprobante->refresh();

                if (in_array($comprobante->estado_sri, ['AUTORIZADO', 'NO_AUTORIZADO', 'DEVUELTA'], true)) {
                    continue;
                }

                if (in_array($comprobante->estado_sri, ['RECIBIDA', 'ENVIADO', 'ERROR_AUTORIZACION'], true) && $comprobante->clave_acceso) {
                    if ($processingService->dispatchAuthorization($comprobante)) {
                        $this->info("Comprobante {$comprobante->id} [{$comprobante->estado_sri}]: Reprogramada consulta de autorización.");
                        $reconciliados++;
                    } else {
                        $this->line("Comprobante {$comprobante->id}: Ya programado o requiere reintento manual tras un fallo.");
                    }
                } else {
                    try {
                        if ($processingService->dispatch($comprobante)) {
                            $this->info("Comprobante {$comprobante->id} [{$comprobante->estado_sri}]: Reprogramado despacho a proceso y firma.");
                            $reconciliados++;
                        } else {
                            $this->line("Comprobante {$comprobante->id}: Ya programado o requiere reintento manual tras un fallo.");
                        }
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        $this->warn("Comprobante {$comprobante->id}: Emisor sin firma configurada todavía.");
                    } catch (\Throwable $e) {
                        $this->error("Comprobante {$comprobante->id}: Error al despachar: " . $e->getMessage());
                    }
                }
            } finally {
                $lock->release();
            }
        }

        $this->info("Reconciliador SRI: Proceso finalizado. {$reconciliados} comprobantes reconciliados.");

        // P12: Depuración segura de certificados temporales huérfanos sin tocar originales
        $this->depurarTemporalesHuerfanos();

        return Command::SUCCESS;
    }

    /**
     * P12: Depurar certificados temporales huérfanos en sri/trabajos con más de 1 hora de antigüedad
     */
    private function depurarTemporalesHuerfanos(): void
    {
        $disk = \Illuminate\Support\Facades\Storage::disk(config('sri.certificate_disk', 'local'));
        if (!$disk->exists('sri/trabajos')) {
            return;
        }

        $files = $disk->files('sri/trabajos');
        $eliminados = 0;
        $limiteTiempo = now()->subHour()->getTimestamp();

        foreach ($files as $file) {
            try {
                if ($disk->lastModified($file) < $limiteTiempo && app(\App\Services\SriTaskRegistry::class)->deleteCertificateIfUnused($file, 3600)) {
                    $eliminados++;
                }
            } catch (\Throwable $e) {
                // Continuar si el archivo está en uso
            }
        }

        if ($eliminados > 0) {
            $this->info("Reconciliador SRI: Se depuraron {$eliminados} certificados temporales huérfanos de sri/trabajos.");
        }
    }
}
