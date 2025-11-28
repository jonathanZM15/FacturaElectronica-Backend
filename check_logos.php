<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Get database connection
$pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

// Check Establishments
echo "=== ESTABLECIMIENTOS ===\n";
$stmts = $pdo->query("SELECT id, codigo, nombre, logo_path, updated_at FROM establecimientos");
while ($row = $stmts->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']}, Código: {$row['codigo']}, Nombre: {$row['nombre']}\n";
    echo "  logo_path: {$row['logo_path']}\n";
    echo "  updated_at: {$row['updated_at']}\n";
    if ($row['logo_path']) {
        $disk_path = storage_path('app/public/' . $row['logo_path']);
        $exists = file_exists($disk_path);
        echo "  File exists: " . ($exists ? 'YES' : 'NO') . "\n";
        echo "  Full path: {$disk_path}\n";
    }
    echo "\n";
}

// Check Companies
echo "\n=== COMPANIES ===\n";
$stmts = $pdo->query("SELECT id, ruc, logo_path, updated_at FROM companies");
while ($row = $stmts->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']}, RUC: {$row['ruc']}\n";
    echo "  logo_path: {$row['logo_path']}\n";
    echo "  updated_at: {$row['updated_at']}\n";
    if ($row['logo_path']) {
        $disk_path = storage_path('app/public/' . $row['logo_path']);
        $exists = file_exists($disk_path);
        echo "  File exists: " . ($exists ? 'YES' : 'NO') . "\n";
        echo "  Full path: {$disk_path}\n";
    }
    echo "\n";
}
