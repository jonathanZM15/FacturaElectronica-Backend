<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "🎫 GENERANDO TOKEN DE ACCESO\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$admin = User::find(1);

if (!$admin) {
    echo "❌ Usuario admin no encontrado\n";
    exit(1);
}

echo "Usuario: {$admin->email}\n";
echo "Rol: {$admin->role->value}\n\n";

// Generar token usando Sanctum
$token = $admin->createToken('api-token')->plainTextToken;

echo "✅ Token generado:\n";
echo $token . "\n\n";

echo "📋 USAR ESTE TOKEN EN LA SOLICITUD:\n";
echo "curl http://localhost:8000/api/usuarios \\\n";
echo "  -H \"Authorization: Bearer {$token}\"\n\n";
