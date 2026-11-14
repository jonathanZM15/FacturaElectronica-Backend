<?php

namespace App\Services;

use App\Jobs\ProcesarFacturaSriJob;
use App\Models\{CompanyCertificate, Comprobante};
use Illuminate\Support\Facades\{Crypt, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceProcessingService
{
    public function certificate(int $companyId): CompanyCertificate
    {
        $certificate = CompanyCertificate::where('emisor_id', $companyId)->first();
        if (!$certificate) throw ValidationException::withMessages(['firma' => ['El Emisor debe configurar la firma de la empresa antes de facturar.']]);
        app(SriSignatureService::class)->verificarP12(Storage::disk(config('sri.certificate_disk', 'local'))->path($certificate->path), $certificate->password);
        return $certificate;
    }

    public function dispatch(Comprobante $invoice, ?CompanyCertificate $certificate = null): void
    {
        $certificate ??= $this->certificate((int) $invoice->emisor_id);
        $disk = Storage::disk(config('sri.certificate_disk', 'local'));
        $path = 'sri/trabajos/'.Str::uuid().'.p12';
        if (!$disk->copy($certificate->path, $path)) throw new \RuntimeException('No se pudo preparar la firma para el procesamiento.');
        try {
            $connection = config('queue.default') === 'sync' ? 'database' : config('queue.default');
            ProcesarFacturaSriJob::dispatch($invoice->id, $path, Crypt::encryptString($certificate->password))->onConnection($connection)->afterCommit();
        } catch (\Throwable $e) {
            $disk->delete($path);
            throw $e;
        }
    }
}
