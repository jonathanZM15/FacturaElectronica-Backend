<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Enums\UserRole;

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Datos del usuario admin
$email = 'admin@test.local';
$password = 'Admin123!';

// Verificar si ya existe
$exists = User::where('email', $email)->first();

if ($exists) {
    echo "❌ El usuario $email ya existe.\n";
    exit(1);
}

// Crear usuario con Enum correcto
$user = User::create([
    'email' => $email,
    'password' => Hash::make($password),
    'nombres' => 'Admin',
    'apellidos' => 'Sistema',
    'cedula' => '0000000000',
    'username' => 'admin_test',
    'role' => UserRole::ADMINISTRADOR,
    'estado' => 'activo',
    'emisor_id' => null,
    'email_verified_at' => now(),
]);

echo "✅ Usuario admin creado exitosamente:\n";
echo "   Email: $email\n";
echo "   Contraseña: $password\n";
echo "   Rol: administrador\n";
echo "   Estado: activo\n";
echo "   Email verificado: Sí\n";
echo "\n🎯 Ya puedes entrar al sistema con estas credenciales.\n";
