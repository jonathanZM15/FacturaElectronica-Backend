<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$items = \App\Models\Establecimiento::limit(3)->get();

echo "Total: " . count($items) . "\n\n";

foreach ($items as $est) {
    echo "===== ID: {$est->id} =====\n";
    echo "Nombre: {$est->nombre}\n";
    echo "Logo path: {$est->logo_path}\n";
    echo "Logo URL: {$est->logo_url}\n";
    
    if ($est->logo_path) {
        $storage_path = storage_path('app/public/' . $est->logo_path);
        $exists = file_exists($storage_path);
        echo "Archivo existe: " . ($exists ? "SÍ" : "NO") . "\n";
        echo "Ruta física: $storage_path\n";
    }
    echo "\n";
}
