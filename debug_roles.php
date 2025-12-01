<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Auth;

echo "🔍 DEBUG: COMPARACIÓN DE ROLES\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$admin = User::find(1);

if (!$admin) {
    echo "❌ Usuario admin no encontrado\n";
    exit(1);
}

echo "Usuario encontrado: {$admin->name}\n";
echo "Role almacenado en BD: {$admin->role->value}\n";
echo "Role tipo: " . get_class($admin->role) . "\n";
echo "Role instanceof UserRole: " . ($admin->role instanceof UserRole ? 'Sí' : 'No') . "\n\n";

echo "--- COMPARACIONES ---\n";

// Intentar diferentes comparaciones
echo "1. String comparison: \$admin->role->value === 'administrador'\n";
echo "   Resultado: " . (($admin->role->value === 'administrador') ? '✅ TRUE' : '❌ FALSE') . "\n\n";

echo "2. Enum comparison: \$admin->role === UserRole::ADMINISTRADOR\n";
echo "   Resultado: " . (($admin->role === UserRole::ADMINISTRADOR) ? '✅ TRUE' : '❌ FALSE') . "\n\n";

echo "3. Value comparison: \$admin->role !== 'administrador'\n";
echo "   Resultado: " . (($admin->role !== 'administrador') ? '❌ TRUE (BLOQUEADO)' : '✅ FALSE (PERMITIDO)') . "\n\n";

echo "4. Direct check: \$admin->role?->value === 'administrador'\n";
echo "   Resultado: " . (($admin->role?->value === 'administrador') ? '✅ TRUE' : '❌ FALSE') . "\n\n";

echo "--- MIDDLEWARE SIMULATION ---\n";

$userRole = $admin->role;
echo "Código del middleware:\n";
echo "  \$userRole = auth()->user()->role;\n";
echo "  if (!\$userRole || \$userRole->value !== 'administrador')\n\n";

if (!$userRole || $userRole->value !== 'administrador') {
    echo "⛔ RESULTADO: BLOQUEADO (403)\n";
} else {
    echo "✅ RESULTADO: PERMITIDO (200)\n";
}

echo "\n";
