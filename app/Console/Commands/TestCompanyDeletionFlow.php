<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\Suscripcion;
use App\Models\Plan;
use App\Services\CompanyBackupService;
use App\Services\CompanyDeletionService;
use App\Mail\CompanyDeletionWarning;
use App\Mail\CompanyDeletionFinalNotice;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class TestCompanyDeletionFlow extends Command
{
    protected $signature = 'test:company-deletion {action=setup}';
    protected $description = 'Test the company deletion flow - setup, send-warnings, send-finals, delete';

    protected CompanyBackupService $backupService;
    protected CompanyDeletionService $deletionService;

    public function __construct(
        CompanyBackupService $backupService,
        CompanyDeletionService $deletionService
    ) {
        parent::__construct();
        $this->backupService = $backupService;
        $this->deletionService = $deletionService;
    }

    public function handle()
    {
        $action = $this->argument('action');

        match($action) {
            'setup' => $this->setupTestCompanies(),
            'warnings' => $this->sendWarnings(),
            'finals' => $this->sendFinals(),
            'delete' => $this->executeDelete(),
            'renew' => $this->testRenewal(),
            'status' => $this->showStatus(),
            default => $this->showHelp(),
        };
    }

    /**
     * Crear 3 empresas de prueba en diferentes estados
     */
    private function setupTestCompanies(): void
    {
        $this->info('🔧 Creando empresas de prueba...');

        // Empresa 1: Inactiva por ~362 días (está por recibir advertencia)
        $company1 = Company::firstOrCreate(
            ['ruc' => '1234567890001'],
            [
                'razon_social' => '[TEST] Empresa por Advertencia',
                'nombre_comercial' => '[TEST] Empresa Advertencia',
                'direccion_matriz' => 'Calle Test 1',
                'regimen_tributario' => 'Régimen Simplificado',
                'obligado_contabilidad' => false,
                'tipo_emision' => 'Online',
                'estado' => 'Vigente',
                'ambiente' => 'Pruebas',
                'ruc' => '1234567890001',
                'correo_remitente' => 'yendermejia0@gmail.com',
                'created_by' => 1,
            ]
        );
        $company1->update(['last_activity_at' => now()->subDays(362)]);

        // Empresa 2: Inactiva por ~365 días (lista para eliminación)
        $company2 = Company::firstOrCreate(
            ['ruc' => '1234567890002'],
            [
                'razon_social' => '[TEST] Empresa por Eliminar',
                'nombre_comercial' => '[TEST] Empresa Eliminar',
                'direccion_matriz' => 'Calle Test 2',
                'regimen_tributario' => 'Régimen Simplificado',
                'obligado_contabilidad' => false,
                'tipo_emision' => 'Online',
                'estado' => 'Vigente',
                'ambiente' => 'Pruebas',
                'ruc' => '1234567890002',
                'correo_remitente' => 'yendermejia0@gmail.com',
                'created_by' => 1,
            ]
        );
        $company2->update(['last_activity_at' => now()->subDays(365)]);

        // Empresa 3: Recién renovada (debe estar activa sin riesgo)
        $company3 = Company::firstOrCreate(
            ['ruc' => '1234567890003'],
            [
                'razon_social' => '[TEST] Empresa Renovada',
                'nombre_comercial' => '[TEST] Empresa Renovada',
                'direccion_matriz' => 'Calle Test 3',
                'regimen_tributario' => 'Régimen Simplificado',
                'obligado_contabilidad' => false,
                'tipo_emision' => 'Online',
                'estado' => 'Vigente',
                'ambiente' => 'Pruebas',
                'ruc' => '1234567890003',
                'correo_remitente' => 'yendermejia0@gmail.com',
                'created_by' => 1,
            ]
        );
        $company3->update(['last_activity_at' => now()->subDays(370)]); // Primero inactiva

        // Ahora renovar empresa 3 (esto debería resetear last_activity_at)
        $this->info('✅ Renovando empresa 3 para resetear contador...');
        $plan = Plan::first();
        if ($plan) {
            Suscripcion::create([
                'emisor_id' => $company3->id,
                'plan_id' => $plan->id,
                'fecha_inicio' => now(),
                'fecha_fin' => now()->addDays(30),
                'monto' => 10.00,
                'cantidad_comprobantes' => 100,
                'comprobantes_usados' => 0,
                'estado_suscripcion' => 'Vigente',
                'forma_pago' => 'Transferencia',
                'created_by_id' => 1,
            ]);
        }

        // Generar backups para poder descargar
        $this->info('📦 Generando backups...');
        try {
            $this->backupService->generateBackup($company1);
            $this->backupService->generateBackup($company2);
            $this->backupService->generateBackup($company3);
        } catch (\Exception $e) {
            $this->warn("⚠️ Error generando backups: {$e->getMessage()}");
        }

        $this->newLine();
        $this->info('✅ Empresas de prueba creadas exitosamente!');
        $this->newLine();

        $this->table(['RUC', 'Empresa', 'Inactividad (días)', 'Estado'], [
            ['1234567890001', '[TEST] Advertencia', '362', 'Por recibir advertencia de 3 días'],
            ['1234567890002', '[TEST] Eliminar', '365', 'Lista para eliminación automática'],
            ['1234567890003', '[TEST] Renovada', '0 (acaba renovar)', '✅ Segura - contador reseteado'],
        ]);

        $this->info('💡 Próximos pasos:');
        $this->line('  1. php artisan test:company-deletion warnings    # Enviar advertencias');
        $this->line('  2. php artisan test:company-deletion finals      # Enviar notificaciones finales');
        $this->line('  3. php artisan test:company-deletion delete      # Ejecutar eliminación');
        $this->line('  4. php artisan test:company-deletion status      # Ver estado actual');
    }

    /**
     * Enviar advertencias (día 362)
     */
    private function sendWarnings(): void
    {
        $this->info('📧 Enviando advertencias de eliminación...');

        // Para testing, obtener todas las empresas de prueba
        $companies = Company::whereIn('ruc', ['1234567890001', '1234567890002', '1234567890003'])
            ->where('last_activity_at', '<=', now()->subDays(360))
            ->get();

        if ($companies->isEmpty()) {
            $this->warn('⚠️ No hay empresas que necesiten advertencia');
            return;
        }

        foreach ($companies as $company) {
            try {
                // Generar nuevo backup
                $this->backupService->generateBackup($company);
                $backupUrl = route('company-deletion.download-backup', $company->id);

                Mail::to($company->correo_remitente)
                    ->send(new CompanyDeletionWarning($company, $backupUrl));

                $company->update([
                    'deletion_warning_sent_at' => now(),
                ]);

                $this->info("✅ Advertencia enviada a: {$company->razon_social} ({$company->correo_remitente})");
            } catch (\Exception $e) {
                $this->error("❌ Error: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('💡 Los correos se pueden ver en: storage/logs/laravel.log o en Mailtrap si está configurado');
    }

    /**
     * Enviar notificaciones finales (día 365)
     */
    private function sendFinals(): void
    {
        $this->info('🔴 Enviando notificaciones finales de eliminación...');

        // Para testing, obtener empresas inactivas hace 365 días
        $companies = Company::whereIn('ruc', ['1234567890001', '1234567890002', '1234567890003'])
            ->where('last_activity_at', '<=', now()->subDays(365))
            ->get();

        if ($companies->isEmpty()) {
            $this->warn('⚠️ No hay empresas que necesiten notificación final');
            return;
        }

        foreach ($companies as $company) {
            try {
                $backupUrl = route('company-deletion.download-backup', $company->id);

                Mail::to($company->correo_remitente)
                    ->send(new CompanyDeletionFinalNotice($company, $backupUrl, 72));

                $company->update([
                    'deletion_final_notice_sent_at' => now(),
                    'scheduled_deletion_at' => now()->addDays(3),
                ]);

                $this->info("✅ Notificación final enviada a: {$company->razon_social} ({$company->correo_remitente})");
            } catch (\Exception $e) {
                $this->error("❌ Error: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('💡 La eliminación se ejecutará en 3 días automáticamente');
    }

    /**
     * Ejecutar eliminación
     */
    private function executeDelete(): void
    {
        $this->info('🗑️ Ejecutando eliminaciones programadas...');

        $companies = $this->deletionService->getCompaniesScheduledForDeletion();

        if ($companies->isEmpty()) {
            $this->warn('⚠️ No hay empresas programadas para eliminación');
            return;
        }

        $this->warn("⚠️ ADVERTENCIA: Se van a ELIMINAR {$companies->count()} empresa(s) permanentemente!");
        if (!$this->confirm('¿Continuar?')) {
            $this->info('Operación cancelada');
            return;
        }

        foreach ($companies as $company) {
            try {
                $this->deletionService->permanentlyDelete($company, 1);
                $this->info("✅ Eliminada: {$company->razon_social}");
            } catch (\Exception $e) {
                $this->error("❌ Error al eliminar: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('✅ Proceso de eliminación completado');
    }

    /**
     * Probar renovación de empresa
     */
    private function testRenewal(): void
    {
        $this->info('🔄 Probando renovación de empresa...');

        $company = Company::where('ruc', '1234567890003')->first();

        if (!$company) {
            $this->error('❌ Empresa de prueba no encontrada');
            return;
        }

        $oldDate = $company->last_activity_at;

        $plan = Plan::first();
        if ($plan) {
            Suscripcion::create([
                'emisor_id' => $company->id,
                'plan_id' => $plan->id,
                'fecha_inicio' => now(),
                'fecha_fin' => now()->addDays(30),
                'monto' => 10.00,
                'cantidad_comprobantes' => 100,
                'comprobantes_usados' => 0,
                'estado_suscripcion' => 'Vigente',
                'forma_pago' => 'Transferencia',
                'created_by_id' => 1,
            ]);
        }

        $company->refresh();
        $newDate = $company->last_activity_at;

        $this->info("Empresa: {$company->razon_social}");
        $this->line("Fecha anterior: $oldDate");
        $this->line("Fecha nueva: $newDate");

        if ($newDate > $oldDate) {
            $this->info('✅ ¡Contador reseteado correctamente! La empresa está protegida de eliminación');
        } else {
            $this->error('❌ El contador NO se reseteó. Hay un problema');
        }
    }

    /**
     * Mostrar estado actual
     */
    private function showStatus(): void
    {
        $this->info('📊 Estado de empresas de prueba:');
        $this->newLine();

        $companies = Company::whereIn('ruc', [
            '1234567890001',
            '1234567890002',
            '1234567890003',
        ])->get();

        foreach ($companies as $company) {
            $inactiveDays = $company->last_activity_at 
                ? now()->diffInDays($company->last_activity_at)
                : null;

            $this->info("📌 {$company->razon_social}");
            $this->line("   RUC: {$company->ruc}");
            $this->line("   Días inactiva: " . ($inactiveDays ?? 'N/A'));
            $this->line("   Última actividad: {$company->last_activity_at}");
            $this->line("   Advertencia enviada: " . ($company->deletion_warning_sent_at ? 'Sí ✅' : 'No ❌'));
            $this->line("   Notif. final enviada: " . ($company->deletion_final_notice_sent_at ? 'Sí ✅' : 'No ❌'));
            $this->line("   Programada para eliminar: " . ($company->scheduled_deletion_at ? $company->scheduled_deletion_at : 'No ❌'));
            $this->newLine();
        }
    }

    /**
     * Mostrar ayuda de comandos
     */
    private function showHelp(): void
    {
        $this->info('🧪 Comandos de prueba disponibles:');
        $this->newLine();

        $this->table(['Comando', 'Descripción'], [
            ['test:company-deletion setup', 'Crear 3 empresas de prueba'],
            ['test:company-deletion warnings', 'Enviar advertencias (día 362)'],
            ['test:company-deletion finals', 'Enviar notificaciones finales (día 365)'],
            ['test:company-deletion delete', 'Ejecutar eliminación automática'],
            ['test:company-deletion renew', 'Probar renovación de empresa'],
            ['test:company-deletion status', 'Ver estado de empresas de prueba'],
        ]);

        $this->newLine();
        $this->info('💡 Ejemplo de flujo completo:');
        $this->line('  $ php artisan test:company-deletion setup');
        $this->line('  $ php artisan test:company-deletion warnings');
        $this->line('  $ php artisan test:company-deletion finals');
        $this->line('  $ php artisan test:company-deletion delete');
    }
}
