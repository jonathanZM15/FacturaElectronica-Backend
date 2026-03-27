<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\CompanyDeletionService;
use Illuminate\Support\Facades\Log;

class ExecuteScheduledCompanyDeletions implements ShouldQueue
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
            // Obtener empresas programadas para ser eliminadas
            $companies = $this->deletionService->getCompaniesScheduledForDeletion();

            Log::info("Found " . count($companies) . " companies scheduled for deletion");

            foreach ($companies as $company) {
                try {
                    // Ejecutar eliminación permanente
                    // El usuario será el sistema (ID 1 o el admin)
                    $adminUser = \App\Models\User::where('role', 'admin')
                        ->orWhere('role', 'superadmin')
                        ->first();

                    $userId = $adminUser?->id ?? 1;

                    if ($this->deletionService->permanentlyDelete($company, $userId)) {
                        Log::info("Company {$company->id} ({$company->ruc}) successfully deleted");
                    } else {
                        Log::error("Failed to delete company {$company->id}");
                    }

                } catch (\Exception $e) {
                    Log::error("Error deleting company {$company->id}: " . $e->getMessage());
                }
            }

        } catch (\Exception $e) {
            Log::error("Error in ExecuteScheduledCompanyDeletions job: " . $e->getMessage());
        }
    }
}
