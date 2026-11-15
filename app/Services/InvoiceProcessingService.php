<?php

namespace App\Services;

use App\Jobs\{ConsultarAutorizacionSriJob, ProcesarFacturaSriJob};
use App\Models\{CompanyCertificate, Comprobante, SriTask};
use Illuminate\Support\Facades\{Bus, Cache, Crypt, DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceProcessingService
{
    private array $signatureBudgets = [];

    public function certificate(int $companyId): CompanyCertificate
    {
        $certificate = CompanyCertificate::where('emisor_id', $companyId)->first();
        if (!$certificate) throw ValidationException::withMessages(['firma' => ['El Emisor debe configurar la firma de la empresa antes de facturar.']]);
        app(SriSignatureService::class)->verificarP12(Storage::disk(config('sri.certificate_disk', 'local'))->path($certificate->path), $certificate->password);
        return $certificate;
    }

    public function signatureOverhead(CompanyCertificate $certificate): int
    {
        $disk = Storage::disk(config('sri.certificate_disk', 'local'));
        $key = hash('sha256', $certificate->path.'|'.$certificate->password.'|'.$disk->lastModified($certificate->path));
        if (isset($this->signatureBudgets[$key])) return $this->signatureBudgets[$key];
        $sample = '<?xml version="1.0" encoding="UTF-8"?><factura id="comprobante" version="2.1.0"/>';
        $signer = app(SriSignatureService::class);
        $signed = $signer->firmarXml($sample, $disk->path($certificate->path), $certificate->password);
        $verification = $signer->verificarXmlFirmado($signed);
        if (!$verification['valida']) throw new \App\Exceptions\SriFirmaException($verification['error']);
        // Includes conservative room for serialization and fixed-length generated IDs.
        return $this->signatureBudgets[$key] = strlen($signed) - strlen($sample) + 64;
    }

    public function dispatch(Comprobante $invoice, ?CompanyCertificate $certificate = null, bool $manual = false): bool
    {
        if (in_array($invoice->estado_sri, ['RECIBIDA', 'ENVIADO', 'ERROR_AUTORIZACION'], true) && $invoice->clave_acceso) {
            return $this->dispatchAuthorization($invoice, 0, $manual);
        }
        return $this->enqueue($invoice, 'procesamiento', $certificate, 0, $manual);
    }

    public function dispatchAuthorization(Comprobante $invoice, int $delay = 0, bool $manual = false): bool
    {
        return $this->enqueue($invoice, 'autorizacion', null, max(0, $delay), $manual);
    }

    private function enqueue(Comprobante $invoice, string $stage, ?CompanyCertificate $certificate, int $delay, bool $manual): bool
    {
        // The task registry and database job are committed together on the same connection.
        // Other queue drivers require a separate publication protocol and are not enabled for SRI V1.
        $connection = (string) config('sri.queue_connection', 'database');
        $queueDatabase = config('queue.connections.'.$connection.'.connection') ?: config('database.default');
        if (config('queue.connections.'.$connection.'.driver') !== 'database' || $queueDatabase !== config('database.default')) {
            throw new \RuntimeException('SRI V1 requiere una cola database en la misma conexión que los comprobantes.');
        }
        $queue = (string) config('sri.queue', 'default');
        $lock = Cache::lock('sri-dispatch-'.$invoice->id, 60);
        if (!$lock->get()) return false;
        $path = null;
        $registry = app(SriTaskRegistry::class);
        try {
            return DB::transaction(function () use ($invoice, $stage, $certificate, $delay, $manual, $connection, $queue, $registry, &$path) {
                $invoice = Comprobante::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
                if (in_array($invoice->estado_sri, ['AUTORIZADO', 'NO_AUTORIZADO', 'DEVUELTA'], true)) return false;
                if ($stage === 'autorizacion' && !$invoice->clave_acceso) return false;
                $task = SriTask::firstOrNew(['comprobante_id' => $invoice->id, 'revision' => $invoice->revision_fiscal, 'etapa' => $stage]);
                if ($task->exists && ($registry->pending($task) || ($task->estado === 'failed' && !$manual))) return false;
                if ($registry->legacyJobPending($invoice->id, $stage)) return false;

                if ($stage === 'procesamiento') {
                    $certificate ??= $this->certificate((int) $invoice->emisor_id);
                    abort_unless((int) $certificate->emisor_id === (int) $invoice->emisor_id, 403, 'La firma no pertenece a esta empresa.');
                    if (strlen((string) $invoice->xml_generado) + $this->signatureOverhead($certificate) > 320000) {
                        throw ValidationException::withMessages(['factura' => ['El comprobante firmado supera el límite de 320 KB del envío individual.']]);
                    }
                    $disk = Storage::disk(config('sri.certificate_disk', 'local'));
                    $path = 'sri/trabajos/'.Str::uuid().'.p12';
                    if (!$disk->copy($certificate->path, $path)) throw new \RuntimeException('No se pudo preparar la firma del comprobante.');
                }
                $task->fill([
                    'estado' => 'pending', 'token' => (string) Str::uuid(), 'queue_connection' => $connection, 'queue' => $queue,
                    'queue_job_id' => null, 'certificate_path' => $path, 'attempts' => 0,
                    'enqueued_at' => now(), 'lease_until' => null, 'last_started_at' => null, 'last_error' => null,
                ])->save();
                $job = $stage === 'procesamiento'
                    ? new ProcesarFacturaSriJob($invoice->id, $path, Crypt::encryptString($certificate->password), $task->id, $task->token, $task->revision)
                    : new ConsultarAutorizacionSriJob($invoice->id, $task->id, $task->token, $task->revision);
                $job->onConnection($connection)->onQueue($queue)->beforeCommit();
                if ($delay) $job->delay(now()->addSeconds($delay));
                $jobId = Bus::dispatch($job);
                $task->update(['queue_job_id' => is_scalar($jobId) ? (string) $jobId : null]);
                return true;
            }, 3);
        } catch (\Throwable $e) {
            if ($path) $registry->deleteCertificateIfUnused($path);
            throw $e;
        } finally {
            $lock->release();
        }
    }
}
