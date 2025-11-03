<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$company = App\Models\Company::first();

if ($company) {
    echo json_encode([
        'id' => $company->id,
        'ruc' => $company->ruc,
        'logo_path' => $company->logo_path,
        'logo_url' => $company->logo_url,
    ], JSON_PRETTY_PRINT);
} else {
    echo "No hay emisores en la base de datos\n";
}
