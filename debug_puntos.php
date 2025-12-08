<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Enums\UserRole;

$user = User::find(5);
echo "Usuario: " . $user->name . " (ID: " . $user->id . ")\n";
echo "Role: " . $user->role->value . "\n";
echo "emisor_id: " . $user->emisor_id . "\n";

// Raw value from DB
$rawPuntos = $user->getAttributes()['puntos_emision_ids'];
echo "puntos_emision_ids (raw from DB): " . var_export($rawPuntos, true) . "\n";

// Check normalizeIds function logic
function normalizeIds($value) {
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
    return is_array($value) ? $value : [];
}

$puntosIds = normalizeIds($rawPuntos);
echo "puntos_emision_ids (normalized): " . json_encode($puntosIds) . "\n";
echo "Is empty: " . (empty($puntosIds) ? 'YES' : 'NO') . "\n";

// Simulate checkPermissions logic
$companyId = 1;
$isAssignedEmissor = ($user->role === UserRole::EMISOR && $user->emisor_id === (int)$companyId);
echo "isAssignedEmissor: " . ($isAssignedEmissor ? 'YES' : 'NO') . "\n";

$hasLimitedAccess = !empty($puntosIds);
echo "hasLimitedAccess: " . ($hasLimitedAccess ? 'YES' : 'NO') . "\n";

echo "\nConclusion:\n";
echo "limited should be: " . ($hasLimitedAccess ? 'true' : 'false') . "\n";
echo "puntos_ids should be: " . json_encode($puntosIds) . "\n";
