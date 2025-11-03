<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$companies = App\Models\Company::all();

echo "Total de emisores: " . $companies->count() . "\n\n";

foreach ($companies as $company) {
    echo "ID: {$company->id}\n";
    echo "RUC: {$company->ruc}\n";
    echo "Razón Social: {$company->razon_social}\n";
    echo "Logo Path: " . ($company->logo_path ?? 'Sin logo') . "\n";
    echo "Logo URL: " . ($company->logo_url ?? 'Sin logo') . "\n";
    echo "---\n\n";
}
