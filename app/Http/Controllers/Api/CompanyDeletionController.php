<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\CompanyDeletionService;
use App\Services\CompanyBackupService;
use App\Services\CompanyRestoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class CompanyDeletionController extends Controller
{
    protected CompanyDeletionService $deletionService;
    protected CompanyBackupService $backupService;
    protected CompanyRestoreService $restoreService;

    public function __construct(
        CompanyDeletionService $deletionService,
        CompanyBackupService $backupService,
        CompanyRestoreService $restoreService
    ) {
        $this->deletionService = $deletionService;
        $this->backupService = $backupService;
        $this->restoreService = $restoreService;
        $this->middleware('auth:sanctum');
        $this->middleware('admin')->except('downloadBackup', 'restoreFromBackup');
    }

    /**
     * Generar backup de un emisor
     */
    public function generateBackup(Request $request, Company $company)
    {
        // Autorizar que solo el dueño o admin pueda solicitar backup
        if (auth()->user()->emisor_id !== $company->id && !auth()->user()->isAdmin()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        try {
            $backupPath = $this->backupService->generateBackup($company);
            
            return response()->json([
                'message' => 'Backup generado exitosamente',
                'backup_path' => $backupPath,
                'download_url' => route('company-deletion.download-backup', $company->id),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al generar backup',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descargar backup existente
     */
    public function downloadBackup(Company $company)
    {
        try {
            $download = $this->backupService->downloadBackup($company);
            
            if (!$download) {
                return response()->json([
                    'message' => 'Backup no disponible'
                ], 404);
            }

            return $download;
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al descargar backup',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Marcar emisor para eliminación (requiere validación de contraseña)
     */
    public function requestDeletion(Request $request, Company $company)
    {
        $request->validate([
            'password' => 'required|string',
            'reason' => 'nullable|string|max:500',
        ]);

        // Verificar contraseña del usuario admin
        if (!Hash::check($request->password, auth()->user()->password)) {
            return response()->json([
                'message' => 'Contraseña incorrecta'
            ], 401);
        }

        try {
            // Generar backup si no existe
            if (!$company->backup_file_path) {
                $this->backupService->generateBackup($company);
            }

            // Marcar para eliminación
            $success = $this->deletionService->markForDeletion(
                $company,
                auth()->id(),
                $request->reason
            );

            if ($success) {
                return response()->json([
                    'message' => 'Emisor marcado para eliminación',
                    'scheduled_deletion_at' => $company->refresh()->scheduled_deletion_at,
                    'backup_download_url' => route('company-deletion.download-backup', $company->id),
                ]);
            }

            return response()->json([
                'message' => 'Error al marcar emisor para eliminación'
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ejecutar eliminación permanente (requiere confirmación y contraseña)
     */
    public function executeImmediateDeletion(Request $request, Company $company)
    {
        $request->validate([
            'password' => 'required|string',
            'confirmation_token' => 'required|string',
        ]);

        // Verificar contraseña
        if (!Hash::check($request->password, auth()->user()->password)) {
            return response()->json([
                'message' => 'Contraseña incorrecta'
            ], 401);
        }

        // Verificar token de confirmación (mitigación de CSRF)
        // En un caso real, esto debería ser un token único generado en el frontend
        if (!hash_equals($request->confirmation_token, hash('sha256', $company->id . auth()->id()))) {
            return response()->json([
                'message' => 'Token de confirmación inválido'
            ], 401);
        }

        try {
            $success = $this->deletionService->permanentlyDelete($company, auth()->id());

            if ($success) {
                return response()->json([
                    'message' => 'Emisor eliminado permanentemente',
                    'deleted_at' => now(),
                ]);
            }

            return response()->json([
                'message' => 'Error al eliminar emisor'
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancelar eliminación programada
     */
    public function cancelDeletion(Request $request, Company $company)
    {
        if (!$company->is_marked_for_deletion) {
            return response()->json([
                'message' => 'Este emisor no está marcado para eliminación'
            ], 400);
        }

        try {
            $company->update([
                'is_marked_for_deletion' => false,
                'scheduled_deletion_at' => null,
                'deletion_requested_by' => null,
            ]);

            return response()->json([
                'message' => 'Eliminación cancelada exitosamente',
                'company' => $company,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cancelar eliminación',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restaurar emisor desde archivo de backup
     */
    public function restoreFromBackup(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            $path = $request->file('backup_file')->store('backups/uploads');
            
            $company = $this->restoreService->restoreFromBackup($path, auth()->id());

            if ($company) {
                return response()->json([
                    'message' => 'Emisor restaurado exitosamente',
                    'company' => $company,
                    'tour_url' => route('companies.show', $company->id),
                ]);
            }

            Storage::delete($path);
            return response()->json([
                'message' => 'Error al restaurar emisor'
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener historial de eliminación de un emisor
     */
    public function getDeletionHistory(Company $company)
    {
        try {
            $logs = $company->deletionLogs()
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'deletion_logs' => $logs,
                'is_marked_for_deletion' => $company->is_marked_for_deletion,
                'scheduled_deletion_at' => $company->scheduled_deletion_at,
                'deletion_warning_sent_at' => $company->deletion_warning_sent_at,
                'deletion_final_notice_sent_at' => $company->deletion_final_notice_sent_at,
                'backup_available' => $company->backup_file_path && Storage::exists($company->backup_file_path),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener empresas inactivas (solo para admin)
     */
    public function getInactiveCompanies()
    {
        try {
            $companies = $this->deletionService->getInactiveCompanies(1);

            return response()->json([
                'inactive_companies' => $companies->map(fn($c) => [
                    'id' => $c->id,
                    'ruc' => $c->ruc,
                    'razon_social' => $c->razon_social,
                    'last_activity_at' => $c->last_activity_at,
                    'days_inactive' => $c->last_activity_at ? now()->diffInDays($c->last_activity_at) : null,
                    'eligible_for_deletion' => true,
                ]),
                'total' => $companies->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
