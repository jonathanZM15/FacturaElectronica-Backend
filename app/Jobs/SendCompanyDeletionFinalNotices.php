<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\CompanyDeletionService;
use App\Mail\CompanyDeletionFinalNotice;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendCompanyDeletionFinalNotices implements ShouldQueue
{
    use Queueable;

    protected CompanyDeletionService $deletionService;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->deletionService = app(CompanyDeletionService::class);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Obtener empresas que han pasado 1 año de inactividad y necesitan notificación final
            $companies = $this->deletionService->getCompaniesNeedingFinalNotice(1);

            foreach ($companies as $company) {
                try {
                    // URL para descargar backup
                    $backupDownloadUrl = route('company-deletion.download-backup', $company->id);

                    // Enviar email final al cliente
                    if ($company->correo_remitente) {
                        Mail::to($company->correo_remitente)
                            ->queue(new CompanyDeletionFinalNotice(
                                $company,
                                $backupDownloadUrl,
                                72 // 3 días en horas
                            ));
                    }

                    // Marcar que se envió la notificación final
                    $company->update([
                        'deletion_final_notice_sent_at' => now(),
                        'scheduled_deletion_at' => now()->addDays(3),
                    ]);

                    Log::info("Final deletion notice sent for company {$company->id}");

                } catch (\Exception $e) {
                    Log::error("Error sending final notice for company {$company->id}: " . $e->getMessage());
                }
            }

        } catch (\Exception $e) {
            Log::error("Error in SendCompanyDeletionFinalNotices job: " . $e->getMessage());
        }
    }
}
