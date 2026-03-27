<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyDeletionLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompanyDeletionService
{
    /**
     * Marcar un emisor para eliminación (cuando se cumpla 3 días después del aviso final)
     */
    public function markForDeletion(Company $company, int $userId, ?string $reason = null): bool
    {
        try {
            DB::beginTransaction();

            $company->update([
                'is_marked_for_deletion' => true,
                'scheduled_deletion_at' => now()->addDays(3),
                'deletion_requested_by' => $userId,
            ]);

            // Registrar en logs
            $this->logDeletionAction($company, 'manual_deletion', $userId, $reason);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error marking company {$company->id} for deletion: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Ejecutar eliminación permanente de un emisor
     */
    public function permanentlyDelete(Company $company, int $userId): bool
    {
        try {
            DB::beginTransaction();

            // Eliminar todos los datos relacionados
            $this->deleteRelatedData($company);

            // Registrar la eliminación final
            CompanyDeletionLog::create([
                'company_id' => $company->id,
                'action_type' => 'auto_deletion',
                'user_id' => $userId,
                'description' => 'Eliminación permanente automática ejecutada',
                'backup_file_path' => $company->backup_file_path,
                'ip_address' => request()->ip(),
                'user_agent' => request()->header('User-Agent'),
                'metadata' => json_encode([
                    'reason' => 'Inactividad de 1 año',
                    'days_after_final_notice' => 3,
                ])
            ]);

            // Eliminar el emisor
            $company->delete();

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error permanently deleting company {$company->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar todos los datos relacionados con un emisor
     */
    private function deleteRelatedData(Company $company): void
    {
        // Eliminar suscripciones
        $company->suscripciones()->forceDelete();

        // Eliminar establecimientos
        $company->establecimientos()?->forceDelete();

        // Eliminar puntos de emisión
        if (method_exists($company, 'puntosEmision')) {
            $company->puntosEmision()?->forceDelete();
        }

        // Desvincularse de usuarios (no eliminarlos, solo la relación)
        if (method_exists($company, 'users')) {
            $company->users()->update(['emisor_id' => null]);
        }

        // Eliminar planes si aplica
        if (method_exists($company, 'planes')) {
            $company->planes()?->forceDelete();
        }
    }

    /**
     * Registrar acciones de eliminación
     */
    private function logDeletionAction(Company $company, string $actionType, ?int $userId = null, ?string $description = null): void
    {
        CompanyDeletionLog::create([
            'company_id' => $company->id,
            'action_type' => $actionType,
            'user_id' => $userId,
            'description' => $description ?? ucfirst(str_replace('_', ' ', $actionType)),
            'backup_file_path' => $company->backup_file_path,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
        ]);
    }

    /**
     * Obtener empresas inactivas por más de 1 año
     */
    public function getInactiveCompanies(int $yearsOfInactivity = 1): \Illuminate\Database\Eloquent\Collection
    {
        $cutoffDate = now()->subYears($yearsOfInactivity);

        return Company::where(function ($query) use ($cutoffDate) {
            $query->where('last_activity_at', '<=', $cutoffDate)
                  ->orWhereNull('last_activity_at');
        })
        ->where('is_marked_for_deletion', false)
        ->get();
    }

    /**
     * Obtener empresas próximas a ser eliminadas
     */
    public function getCompaniesScheduledForDeletion(): \Illuminate\Database\Eloquent\Collection
    {
        return Company::where('is_marked_for_deletion', true)
            ->where('scheduled_deletion_at', '<=', now())
            ->get();
    }

    /**
     * Obtener empresas que necesitan aviso de eliminación (3 días antes)
     */
    public function getCompaniesNeedingDeletionWarning(int $yearsOfInactivity = 1): \Illuminate\Database\Eloquent\Collection
    {
        $cutoffDate = now()->subYears($yearsOfInactivity)->addDays(3);

        return Company::where(function ($query) use ($cutoffDate) {
            $query->where('last_activity_at', '<=', $cutoffDate)
                  ->orWhereNull('last_activity_at');
        })
        ->whereNull('deletion_warning_sent_at')
        ->where('is_marked_for_deletion', false)
        ->get();
    }

    /**
     * Obtener empresas que necesitan aviso final (día de eliminación)
     */
    public function getCompaniesNeedingFinalNotice(int $yearsOfInactivity = 1): \Illuminate\Database\Eloquent\Collection
    {
        $cutoffDate = now()->subYears($yearsOfInactivity);

        return Company::where(function ($query) use ($cutoffDate) {
            $query->where('last_activity_at', '<=', $cutoffDate)
                  ->orWhereNull('last_activity_at');
        })
        ->whereNotNull('deletion_warning_sent_at')
        ->whereNull('deletion_final_notice_sent_at')
        ->where('is_marked_for_deletion', false)
        ->get();
    }
}
