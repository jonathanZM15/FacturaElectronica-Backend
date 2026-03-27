<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class CompanyRestoreService
{
    /**
     * Restaurar emisor desde archivo de backup Excel
     */
    public function restoreFromBackup(string $filePath, int $userId): ?Company
    {
        try {
            DB::beginTransaction();

            // Leer el Excel
            $data = Excel::toArray(new \stdClass(), storage_path("app/{$filePath}"));

            // Extraer datos del primer sheet
            $companyData = $this->extractCompanyData($data[0]);

            // Crear el emisor nuevamente
            $company = Company::create(array_merge($companyData, [
                'created_by' => $userId,
                'updated_by' => $userId,
                'estado' => 'Vigente',
                'last_activity_at' => now(),
                'is_marked_for_deletion' => false,
                'deletion_warning_sent_at' => null,
                'deletion_final_notice_sent_at' => null,
                'scheduled_deletion_at' => null,
            ]));

            // Restaurar usuarios vinculados
            if (isset($data['0']) && count($data[0]) > 12) {
                $this->restoreUsers($company, $data[0]);
            }

            // Restaurar suscripciones
            if (isset($data['1']) && count($data[1]) > 0) {
                $this->restoreSubscriptions($company, $data[1]);
            }

            DB::commit();

            // Registrar en logs
            $this->logRestoration($company, $userId);

            return $company;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error restoring company from backup: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extraer datos generales del emisor del Excel
     */
    private function extractCompanyData(array $rows): array
    {
        $data = [];

        foreach ($rows as $index => $row) {
            if (count($row) >= 2) {
                match ($row[0] ?? null) {
                    'RUC' => $data['ruc'] = $row[1],
                    'Razón Social' => $data['razon_social'] = $row[1],
                    'Nombre Comercial' => $data['nombre_comercial'] = $row[1],
                    'Dirección Matriz' => $data['direccion_matriz'] = $row[1],
                    'Régimen Tributario' => $data['regimen_tributario'] = $row[1],
                    'Obligado a Contabilidad' => $data['obligado_contabilidad'] = $row[1] === 'Sí',
                    'Contribuyente Especial' => $data['contribuyente_especial'] = $row[1] === 'Sí',
                    'Agente de Retención' => $data['agente_retencion'] = $row[1] === 'Sí',
                    default => null,
                };
            }
        }

        return $data;
    }

    /**
     * Restaurar usuarios desde datos del Excel
     */
    private function restoreUsers(Company $company, array $data): void
    {
        // Búscar el índice donde inicia la sección de usuarios
        $startIndex = 0;
        foreach ($data as $index => $row) {
            if ($row[0] === 'USUARIOS VINCULADOS') {
                $startIndex = $index + 2;
                break;
            }
        }

        // Procesar cada usuario
        for ($i = $startIndex; $i < count($data); $i++) {
            $row = $data[$i];

            if (count($row) < 2 || $row[0] === 'PLANES DE FACTURACIÓN' || $row[0] === '') {
                break;
            }

            // Buscar usuario existente por username o crear uno nuevo
            $user = User::where('username', $row[1] ?? '')->first();

            if ($user) {
                // Vincular usuario existente
                $user->update(['emisor_id' => $company->id]);
            } else if (!empty($row[1])) {
                // Crear usuario nuevo si tiene datos suficientes
                // Nota: Se debe manejar cuidadosamente para no crear usuarios sin los campos requeridos
                Log::warning("Usuario {$row[1]} no encontrado durante restauración de {$company->id}");
            }
        }
    }

    /**
     * Restaurar suscripciones desde datos del Excel
     */
    private function restoreSubscriptions(Company $company, array $data): void
    {
        // Búscar índice de suscripciones
        $startIndex = 0;
        foreach ($data as $index => $row) {
            if (isset($row[0]) && $row[0] === 'SUSCRIPCIONES') {
                $startIndex = $index + 2;
                break;
            }
        }

        for ($i = $startIndex; $i < count($data); $i++) {
            $row = $data[$i];

            // Validar que hay datos
            if (count($row) < 4 || empty($row[1])) {
                break;
            }

            // Buscar el plan
            $plan = Plan::where('nombre', $row[1])->first();

            if ($plan) {
                Suscripcion::create([
                    'emisor_id' => $company->id,
                    'plan_id' => $plan->id,
                    'fecha_inicio' => $this->parseDate($row[2]),
                    'fecha_fin' => $this->parseDate($row[3]),
                    'monto' => floatval($row[4] ?? 0),
                    'cantidad_comprobantes' => intval($row[5] ?? 0),
                    'comprobantes_usados' => 0,
                    'estado_suscripcion' => $row[6] ?? 'Vigente',
                    'forma_pago' => $row[7] ?? 'Transferencia',
                    'created_by_id' => auth()->id(),
                ]);
            }
        }
    }

    /**
     * Parsear fecha desde Excel
     */
    private function parseDate($value): Carbon
    {
        if (is_numeric($value)) {
            return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value));
        }

        return Carbon::parse($value);
    }

    /**
     * Registrar restauración en logs
     */
    private function logRestoration(Company $company, int $userId): void
    {
        \App\Models\CompanyDeletionLog::create([
            'company_id' => $company->id,
            'action_type' => 'restored',
            'user_id' => $userId,
            'description' => 'Emisor restaurado desde archivo de backup',
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'metadata' => json_encode([
                'restored_at' => now(),
                'restored_by_user_id' => $userId,
            ])
        ]);
    }
}
