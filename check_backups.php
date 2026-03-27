<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Company;

// Obtener IDs de las empresas de prueba
$companies = Company::whereIn('ruc', ['1234567890001', '1234567890002', '1234567890003'])->select('id', 'razon_social', 'backup_file_path')->get();

echo "\n📦 INFORMACIÓN DE BACKUPS:\n";
echo "══════════════════════════════════════════════════════════════\n";

foreach($companies as $company) {
    echo "\n🏢 {$company->razon_social} (ID: {$company->id})\n";
    
    if ($company->backup_file_path) {
        echo "   ✅ Backup: {$company->backup_file_path}\n";
        $storageUrl = config('app.url') . '/api/company-deletion/' . $company->id . '/download-backup';
        echo "   📥 Descargar URL:\n";
        echo "      $storageUrl\n";
        
        // Verificar que exista
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($company->backup_file_path)) {
            echo "   ✅ Archivo existe en storage\n";
        } else {
            echo "   ❌ Archivo NO existe\n";
        }
    } else {
        echo "   ❌ Sin backup aún\n";
    }
}

echo "\n══════════════════════════════════════════════════════════════\n";
echo "\n✅ Script completado\n";
