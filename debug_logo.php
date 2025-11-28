<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$company = \App\Models\Company::first();

if (!$company) {
    echo "No hay emisores\n";
    exit(1);
}

echo "=== INFO DEL EMISOR ===\n";
echo "ID: {$company->id}\n";
echo "RUC: {$company->ruc}\n";
echo "Logo path: {$company->logo_path}\n";
echo "Logo URL: {$company->logo_url}\n";
echo "Updated at: {$company->updated_at}\n\n";

if ($company->logo_path) {
    $storage_path = storage_path('app/public/' . $company->logo_path);
    $exists = file_exists($storage_path);
    $size = $exists ? filesize($storage_path) : 0;
    $modified = $exists ? filemtime($storage_path) : 0;
    
    echo "=== ARCHIVO EN DISK ===\n";
    echo "Ruta: $storage_path\n";
    echo "Existe: " . ($exists ? "SÍ" : "NO") . "\n";
    echo "Tamaño: $size bytes\n";
    echo "Última modificación: " . ($modified ? date('Y-m-d H:i:s', $modified) : 'N/A') . "\n";
    echo "Timestamp de modified: $modified\n";
    echo "Timestamp de updated_at DB: " . $company->updated_at->timestamp . "\n";
}
