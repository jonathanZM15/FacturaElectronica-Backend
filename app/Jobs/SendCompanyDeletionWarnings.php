<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\CompanyDeletionService;
use App\Services\CompanyBackupService;
use App\Mail\CompanyDeletionWarning;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendCompanyDeletionWarnings implements ShouldQueue
{
    use Queueable;

    protected CompanyDeletionService $deletionService;
    protected CompanyBackupService $backupService;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->deletionService = app(CompanyDeletionService::class);
        $this->backupService = app(CompanyBackupService::class);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Obtener empresas inactivas por 1 año que aún no han recibido advertencia
            $companies = $this->deletionService->getCompaniesNeedingDeletionWarning(1);

            foreach ($companies as $company) {
                try {
                    // Generar backup si no existe
                    if (!$company->backup_file_path) {
                        $this->backupService->generateBackup($company);
                    }

                    // Preparar URL para descargar backup
                    $backupDownloadUrl = route('company-deletion.download-backup', $company->id);

                    // Enviar email al cliente principal
                    if ($company->correo_remitente) {
                        Mail::to($company->correo_remitente)
                            ->queue(new CompanyDeletionWarning($company, $backupDownloadUrl));
                    }

                    // Marcar que se envió la advertencia
                    $company->update([
                        'deletion_warning_sent_at' => now(),
                    ]);

                    Log::info("Deletion warning sent for company {$company->id}");

                } catch (\Exception $e) {
                    Log::error("Error sending deletion warning for company {$company->id}: " . $e->getMessage());
                }
            }

        } catch (\Exception $e) {
            Log::error("Error in SendCompanyDeletionWarnings job: " . $e->getMessage());
        }
    }
}
