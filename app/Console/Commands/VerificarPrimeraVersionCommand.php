<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\InvoiceProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class VerificarPrimeraVersionCommand extends Command
{
    protected $signature = 'v1:verificar {--emisor= : ID de la empresa a revisar} {--json : Mostrar resultados estructurados}';
    protected $description = 'Revisa preparación local y configuración de V1 sin emitir ni contactar SRI o SMTP';

    public function handle(): int
    {
        $checks = [];
        $add = function (string $name, bool $ok, string $detail) use (&$checks) { $checks[] = ['control' => $name, 'correcto' => $ok, 'detalle' => $detail]; };
        $connection = config('sri.queue_connection', 'database');
        $add('Cola transaccional', config('queue.connections.'.$connection.'.driver') === 'database'
            && (config('queue.connections.'.$connection.'.connection') ?: config('database.default')) === config('database.default'), 'Usar database en la misma conexión que los comprobantes.');
        $add('Reserva y bloqueo', (int) config('queue.connections.'.$connection.'.retry_after') >= 180, 'retry_after debe ser al menos 180 segundos; worker timeout de 60 segundos.');
        $add('Plazo de consultas', (int) config('sri.max_authorization_attempts') * max(1, (int) config('sri.retry_after_seconds')) >= (int) config('sri.authorization_window_seconds'), 'El presupuesto debe permitir el plazo configurado; confirmar parámetros del worker.');
        $add('Bloqueos compartidos', !in_array(config('cache.default'), ['array', 'null'], true), 'API, scheduler y workers deben compartir almacenamiento de bloqueos.');
        $add('Registro de tareas', Schema::hasTable('sri_tareas') && Schema::hasColumn('comprobantes', 'revision_fiscal'), 'Aplicar migraciones después de respaldar y ensayar la restauración.');
        $add('Revisión de IVA', now('America/Guayaquil')->toDateString() <= (config('iva.reviewed_until') ?: ''), 'Revisar las normas antes del límite operativo configurado; no equivale a caducidad legal.');
        $provider = trim((string) config('sri.proveedor_ruc'));
        $add('Proveedor del software', !config('sri.proveedor_requerido', true) || preg_match('/^(?!0{13}$)(?!9{13}$)[0-9]{13}$/D', $provider) === 1, 'Pendiente de dato real y comprobación registral; el formato por sí solo no acredita registro.');
        $add('Correo configurado', !in_array(config('mail.default'), ['array', 'log', null], true) && filled(config('mail.from.address'))
            && !str_contains((string) config('mail.from.address'), '@example.')
            && !str_contains((string) config('mail.mailers.smtp.host'), 'example.'), 'La entrega a un buzón controlado debe comprobarse aparte.');
        if ($id = $this->option('emisor')) {
            $company = Company::find($id);
            $add('Empresa', $company !== null && $company->estado === 'ACTIVO', 'Empresa existente y activa.');
            if ($company?->regimen_tributario === 'RIMPE_POPULAR') {
                $add('Perfil RIMPE Popular', (bool) config('sri.rimpe_popular_habilitado'), 'Mantener deshabilitado hasta resolver y validar con SRI la discrepancia de los artefactos oficiales.');
            }
            $validCertificate = false;
            if ($company) {
                try { app(InvoiceProcessingService::class)->certificate((int) $company->id); $validCertificate = true; }
                catch (\Throwable $e) { /* Do not print certificate paths, keys or passwords. */ }
            }
            $add('Certificado', $validCertificate, 'Debe existir un certificado utilizable y vigente de la empresa; la aceptación externa sigue pendiente.');
        } else {
            $add('Empresa y certificado', false, 'Ejecutar también con --emisor=ID para revisar la empresa concreta.');
        }
        if ($this->option('json')) $this->line(json_encode(['verificacion_externa_realizada' => false, 'controles' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        else {
            $this->table(['Control', 'Configuración', 'Detalle'], array_map(fn ($c) => [$c['control'], $c['correcto'] ? 'Correcta' : 'Pendiente', $c['detalle']], $checks));
            $this->warn('Este comando no prueba autorización del SRI, entrega real de correo, worker activo ni aceptación de usuarios.');
        }
        return collect($checks)->every(fn ($c) => $c['correcto']) ? self::SUCCESS : self::FAILURE;
    }
}
