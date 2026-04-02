<?php
require 'bootstrap/app.php';

echo "=== AUDITORÍA DEL SISTEMA ===\n\n";

// 1. Verificar BD
echo "1. BD:\n";
echo (DB::connection()->getPdo() ? "   ✓ Conectada\n" : "   ✗ No conecta\n");

// 2. Verificar tablas principales
echo "\n2. Tablas principales:\n";
$tables = ['emisores', 'users', 'establecimientos', 'puntos_emision', 'emisor_deletion_logs'];
foreach ($tables as $t) {
    echo "   " . (Schema::hasTable($t) ? "✓" : "✗") . " $t\n";
}

// 3. Verificar migraciones
echo "\n3. Migraciones:\n";
echo "   ✓ " . DB::table('migrations')->count() . " migraciones aplicadas\n";

// 4. Verificar Controllers
echo "\n4. Controladores:\n";
$controllers = [
    'CompanyDeletionController' => 'app/Http/Controllers/Api/CompanyDeletionController.php',
    'EmisorController' => 'app/Http/Controllers/EmisorController.php',
    'EstablecimientoController' => 'app/Http/Controllers/EstablecimientoController.php',
    'PuntoEmisionController' => 'app/Http/Controllers/PuntoEmisionController.php'
];
foreach ($controllers as $name => $path) {
    echo "   " . (file_exists($path) ? "✓" : "✗") . " $name\n";
}

// 5. Verificar Services
echo "\n5. Servicios:\n";
$services = [
    'CompanyDeletionService' => 'app/Services/CompanyDeletionService.php',
    'CompanyBackupService' => 'app/Services/CompanyBackupService.php',
    'CompanyRestoreService' => 'app/Services/CompanyRestoreService.php'
];
foreach ($services as $name => $path) {
    echo "   " . (file_exists($path) ? "✓" : "✗") . " $name\n";
}

// 6. Verificar Jobs
echo "\n6. Jobs:\n";
$jobs = [
    'ExecuteScheduledCompanyDeletions' => 'app/Jobs/ExecuteScheduledCompanyDeletions.php',
    'SendCompanyDeletionWarnings' => 'app/Jobs/SendCompanyDeletionWarnings.php',
    'SendCompanyDeletionFinalNotices' => 'app/Jobs/SendCompanyDeletionFinalNotices.php'
];
foreach ($jobs as $name => $path) {
    echo "   " . (file_exists($path) ? "✓" : "✗") . " $name\n";
}

echo "\n=== FIN AUDITORÍA ===\n";
