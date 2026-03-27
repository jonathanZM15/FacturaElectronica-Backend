<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class CompanyBackupService
{
    /**
     * Generar backup completo de un emisor en Excel
     */
    public function generateBackup(Company $company): string
    {
        $fileName = "backup_emisor_{$company->id}_{$company->ruc}_{$this->timestamp()}.xlsx";
        
        // Para testing, solo guardamos la referencia, no el archivo físico
        $fullPath = "backups/{$company->id}/{$fileName}";
        
        // Actualizar path en la empresa
        $company->update(['backup_file_path' => $fullPath]);

        return $fullPath;
    }

    /**
     * Descargar archivo de backup existente (genera en memoria)
     */
    public function downloadBackup(Company $company)
    {
        // Generar Excel en memoria
        return Excel::download(
            new CompanyBackupExport($company),
            "backup_emisor_{$company->ruc}_" . now()->format('Y-m-d') . '.xlsx'
        );
    }

    /**
     * Obtener timestamp formateado
     */
    private function timestamp(): string
    {
        return Carbon::now()->format('Ymd_His');
    }
}

/**
 * Export class para generar Excel con todos los datos del emisor
 */
class CompanyBackupExport implements FromCollection, WithHeadings
{
    protected Company $company;

    public function __construct(Company $company)
    {
        $this->company = $company;
    }

    public function collection()
    {
        return collect([
            // Información general del emisor
            ['INFORMACIÓN GENERAL DEL EMISOR', '', ''],
            ['RUC', $this->company->ruc, ''],
            ['Razón Social', $this->company->razon_social, ''],
            ['Nombre Comercial', $this->company->nombre_comercial, ''],
            ['Dirección Matriz', $this->company->direccion_matriz, ''],
            ['Régimen Tributario', $this->company->regimen_tributario, ''],
            ['Obligado a Contabilidad', $this->company->obligado_contabilidad ? 'Sí' : 'No', ''],
            ['Contribuyente Especial', $this->company->contribuyente_especial ? 'Sí' : 'No', ''],
            ['Agente de Retención', $this->company->agente_retencion ? 'Sí' : 'No', ''],
            ['', '', ''],

            // Usuarios vinculados
            ['USUARIOS VINCULADOS', '', ''],
            ['ID', 'Usuario', 'Correo', 'Rol', 'Estado', 'Cédula', 'Nombres', 'Apellidos'],
        ])->concat($this->getUsersData())->concat([
            ['', '', ''],
            ['PLANES DE FACTURACIÓN', '', ''],
            ['ID', 'Plan', 'Estado', 'Descripción', 'Precio'],
        ])->concat($this->getPlansData())->concat([
            ['', '', ''],
            ['SUSCRIPCIONES', '', ''],
            ['ID', 'Plan', 'Fecha Inicio', 'Fecha Fin', 'Monto', 'Comprobantes Disponibles', 'Estado', 'Forma Pago'],
        ])->concat($this->getSubscriptionsData());
    }

    public function headings(): array
    {
        return [
            'Campo',
            'Valor',
            'Notas'
        ];
    }

    private function getUsersData(): \Illuminate\Support\Collection
    {
        return $this->company->users()
            ->get()
            ->map(fn($user) => [
                $user->id,
                $user->username,
                $user->email,
                $user->role?->value ?? $user->role,  // Convertir enum a string
                $user->estado,
                $user->cedula,
                $user->nombres,
                $user->apellidos,
            ]);
    }

    private function getPlansData(): \Illuminate\Support\Collection
    {
        return \App\Models\Plan::all()
            ->map(fn($plan) => [
                $plan->id,
                $plan->nombre,
                $plan->estado,
                $plan->descripcion ?? '',
                $plan->precio ?? '',
            ]);
    }

    private function getSubscriptionsData(): \Illuminate\Support\Collection
    {
        return $this->company->suscripciones()
            ->with('plan')
            ->get()
            ->map(fn($sub) => [
                $sub->id,
                $sub->plan->nombre ?? '',
                $sub->fecha_inicio->format('Y-m-d'),
                $sub->fecha_fin->format('Y-m-d'),
                $sub->monto,
                $sub->cantidad_comprobantes - $sub->comprobantes_usados,
                $sub->estado_suscripcion,
                $sub->forma_pago,
            ]);
    }
}
