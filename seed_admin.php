<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

try {
    // Primero, verifica si existe ya
    $exists = User::where('email', 'admin@factura.local')->exists();
    
    if (!$exists) {
        User::create([
            'name' => 'Admin Factura',
            'email' => 'admin@factura.local',
            'password' => Hash::make('admin123'),
            'role' => 'administrador',
            'estado' => 'activo',
        ]);
        echo "✅ Usuario admin creado: admin@factura.local / admin123\n";
    } else {
        echo "⚠️  Usuario admin ya existe\n";
    }
    
    // Usuario cajero de prueba
    $exists2 = User::where('email', 'cajero@test.local')->exists();
    if (!$exists2) {
        User::create([
            'name' => 'Test Cajero',
            'email' => 'cajero@test.local',
            'password' => Hash::make('test123'),
            'role' => 'cajero',
            'estado' => 'activo',
        ]);
        echo "✅ Usuario cajero creado: cajero@test.local / test123\n";
    } else {
        echo "⚠️  Usuario cajero ya existe\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getFile() . ":" . $e->getLine() . "\n";
}
