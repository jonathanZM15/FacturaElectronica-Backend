<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Company;
use Illuminate\Support\Facades\Http;

echo "\n🧪 PRUEBA DE DESCARGA Y EXPORTACIÓN\n";
echo "═══════════════════════════════════════════════════════════════\n";

// Obtener la empresa de prueba
$company = Company::where('ruc', '1234567890002')->first();

if (!$company) {
    echo "❌ Empresa no encontrada\n";
    exit;
}

echo "\n🏢 Empresa: {$company->razon_social} (ID: {$company->id})\n";

// URL del endpoint
$baseUrl = 'http://localhost:8000';
$downloadUrl = "{$baseUrl}/api/company-deletion/{$company->id}/download-backup";

echo "\n📥 Intentando descargar backup...\n";
echo "   URL: $downloadUrl\n";

try {
    $response = Http::timeout(30)->get($downloadUrl);
    
    if ($response->successful()) {
        echo "\n✅ ÉXITO - Descarga funcionando\n";
        echo "   Status: {$response->status()}\n";
        echo "   Content-Type: {$response->header('Content-Type')}\n";
        echo "   Content-Disposition: {$response->header('Content-Disposition')}\n";
        
        // Tamaño del archivo
        $size = strlen($response->body());
        echo "   Tamaño: " . number_format($size) . " bytes\n";
        
        // Guardarlo localmente para verificar
        $testFile = "test_backup_{$company->id}.xlsx";
        file_put_contents($testFile, $response->body());
        echo "\n   ✅ Archivo guardado en: $testFile\n";
        
        // Verificar si es un Excel válido
        if (strpos($response->body(), 'PK') === 0) {
            echo "   ✅ Formato válido: Archivo ZIP/Excel detectado\n";
        } else {
            echo "   ⚠️ Advertencia: Formato podría no ser Excel\n";
        }
    } else {
        echo "\n❌ ERROR - Status: {$response->status()}\n";
        echo "   Response: {$response->body()}\n";
    }
} catch (\Exception $e) {
    echo "\n❌ EXCEPCIÓN: {$e->getMessage()}\n";
    
    // Intenta con curl como alternativa
    echo "\n🔄 Intentando con método alternativo...\n";
    $ch = curl_init($downloadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $output = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "✅ ÉXITO (curl) - Descarga funcionando\n";
        echo "   Status: $httpCode\n";
        echo "   Content-Type: $contentType\n";
        echo "   Tamaño: " . number_format(strlen($output)) . " bytes\n";
        
        $testFile = "test_backup_curl_{$company->id}.xlsx";
        file_put_contents($testFile, $output);
        echo "   ✅ Archivo guardado en: $testFile\n";
    } else {
        echo "❌ Error HTTP: $httpCode\n";
    }
}

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "✅ Test completado\n";
