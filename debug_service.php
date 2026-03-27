<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Company;

// Get test company
$company = Company::where('ruc', '1234567890002')->first();

if (!$company) {
    echo "Empresa no encontrada\n";
    exit(1);
}

echo "Empresa: ID={$company->id}\n";
echo "Backup: {$company->backup_file_path}\n\n";

// Try to call the backup service directly
try {
    $service = app(\App\Services\CompanyBackupService::class);
    echo "Service loaded successfully\n";
    
    // Try to download
    $response = $service->downloadBackup($company);
    echo "Response: " . get_class($response) . "\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}\n";
    echo "Line: {$e->getLine()}\n";
    echo "\nStackTrace:\n";
    echo $e->getTraceAsString();
}
