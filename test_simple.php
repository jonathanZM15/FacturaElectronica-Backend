<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Company;

// Get test company
$company = Company::where('ruc', '1234567890002')->first();

if (!$company) {
    fwrite(STDERR, "Empresa no encontrada\n");
    exit(1);
}

fwrite(STDERR, "Empresa encontrada: ID={$company->id}, RUC={$company->ruc}\n");

// Test if company has backup
if ($company->backup_file_path) {
    fwrite(STDERR, "Backup path: {$company->backup_file_path}\n");
} else {
    fwrite(STDERR, "❌ No backup path set\n");
}

// Test the endpoint directly via Laravel routing
echo "HTTP Test:\n";
$url = "/api/company-deletion/{$company->id}/download-backup";
fwrite(STDERR, "Endpoint: $url\n");

// Try to fetch via HTTP
$ch = curl_init("http://localhost:8000{$url}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

fwrite(STDERR, "Status Code: $httpCode\n");
fwrite(STDERR, "Content-Type: $contentType\n");
fwrite(STDERR, "Response Size: " . strlen($response) . " bytes\n");

if ($httpCode === 200) {
    // Separate headers and body
    $headerEnd = strpos($response, "\r\n\r\n");
    if ($headerEnd !== false) {
        $body = substr($response, $headerEnd + 4);
        fwrite(STDERR, "Body Size: " . strlen($body) . " bytes\n");
        
        // Check if it's valid Excel
        if (strpos($body, 'PK') === 0) {
            fwrite(STDERR, "✅ Valid Excel/ZIP detected\n");
        } else {
            fwrite(STDERR, "❌ Not valid Excel format\n");
            echo substr($body, 0, 200);
        }
    }
} else {
    echo substr($response, 0, 500);
}

curl_close($ch);
