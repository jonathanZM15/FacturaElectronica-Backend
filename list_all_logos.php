<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get all companies with logos
$companies = \App\Models\Company::whereNotNull('logo_path')->get();

echo "=== TODOS LOS LOGOS ===\n";
echo "Total: " . count($companies) . "\n\n";

foreach ($companies as $company) {
    $storage_path = storage_path('app/public/' . $company->logo_path);
    $exists = file_exists($storage_path);
    $modified = $exists ? filemtime($storage_path) : null;
    
    echo "ID: {$company->id}\n";
    echo "  RUC: {$company->ruc}\n";
    echo "  Path: {$company->logo_path}\n";
    echo "  Archivo existe: " . ($exists ? "SÍ" : "NO") . "\n";
    if ($modified) {
        echo "  Modificado: " . date('Y-m-d H:i:s', $modified) . "\n";
    }
    echo "  Updated at DB: {$company->updated_at}\n";
    echo "  URL: {$company->logo_url}\n";
    echo "\n";
}
