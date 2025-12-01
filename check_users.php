<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "📊 ESTADO ACTUAL DE USUARIOS EN BD\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$users = User::all();

if ($users->count() === 0) {
    echo "❌ NO HAY USUARIOS EN LA BD\n";
} else {
    echo "✅ USUARIOS ENCONTRADOS: " . $users->count() . "\n\n";
    
    foreach ($users as $user) {
        echo "ID: {$user->id}\n";
        echo "  Nombre: {$user->name}\n";
        echo "  Email: {$user->email}\n";
        echo "  Rol: {$user->role->value}\n";
        echo "  Rol tipo: " . get_class($user->role) . "\n";
        echo "  Estado: {$user->estado}\n";
        echo "  Creado: {$user->created_at}\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    }
}

echo "\n";
