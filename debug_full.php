<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\PuntoEmision;
use App\Enums\UserRole;

$user = User::find(5);
echo "=== Usuario ===" . PHP_EOL;
echo "ID: " . $user->id . PHP_EOL;
echo "Name: " . $user->name . PHP_EOL;
echo "Role: " . $user->role->value . PHP_EOL;
echo "emisor_id: " . $user->emisor_id . PHP_EOL;

echo PHP_EOL . "=== Datos RAW de la BD ===" . PHP_EOL;
$rawEstab = $user->getAttributes()['establecimientos_ids'];
$rawPuntos = $user->getAttributes()['puntos_emision_ids'];
echo "establecimientos_ids RAW: " . var_export($rawEstab, true) . PHP_EOL;
echo "puntos_emision_ids RAW: " . var_export($rawPuntos, true) . PHP_EOL;

echo PHP_EOL . "=== Simulando normalizeIds ===" . PHP_EOL;

function normalizeIds($value): array
{
    if (is_array($value)) {
        return $value;
    }

    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Si el resultado es un string (doble codificación), decodificar de nuevo
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }
            if (is_array($decoded)) {
                return $decoded;
            }
        }
    }

    if (is_numeric($value)) {
        return [(int) $value];
    }

    return [];
}

$puntosIds = normalizeIds($rawPuntos);
echo "puntos_emision_ids normalized: " . json_encode($puntosIds) . PHP_EOL;
echo "empty(\$puntosIds): " . (empty($puntosIds) ? 'true' : 'false') . PHP_EOL;

echo PHP_EOL . "=== Simulando checkPermissions ===" . PHP_EOL;
$companyId = 1;
$isAssignedEmissor = ($user->role === UserRole::EMISOR && $user->emisor_id === (int)$companyId);
echo "isAssignedEmissor: " . ($isAssignedEmissor ? 'true' : 'false') . PHP_EOL;

$hasLimitedAccess = !empty($puntosIds);
echo "hasLimitedAccess: " . ($hasLimitedAccess ? 'true' : 'false') . PHP_EOL;

echo PHP_EOL . "=== Lo que debería retornar checkPermissions ===" . PHP_EOL;
echo "limited: " . ($hasLimitedAccess ? 'true' : 'false') . PHP_EOL;
echo "puntos_ids: " . json_encode($puntosIds) . PHP_EOL;

echo PHP_EOL . "=== Puntos del establecimiento 1 ===" . PHP_EOL;
$puntos = PuntoEmision::where('company_id', 1)->where('establecimiento_id', 1)->get();
foreach ($puntos as $p) {
    $inAssigned = in_array($p->id, $puntosIds) ? 'SI' : 'NO';
    echo "Punto ID: {$p->id}, codigo: {$p->codigo}, nombre: {$p->nombre} - En asignados: {$inAssigned}" . PHP_EOL;
}

echo PHP_EOL . "=== Filtrando puntos ===" . PHP_EOL;
if ($hasLimitedAccess && !empty($puntosIds)) {
    $filtered = $puntos->filter(function ($punto) use ($puntosIds) {
        return in_array($punto->id, $puntosIds);
    });
    echo "Puntos después de filtrar:" . PHP_EOL;
    foreach ($filtered as $p) {
        echo "  - ID: {$p->id}, codigo: {$p->codigo}, nombre: {$p->nombre}" . PHP_EOL;
    }
} else {
    echo "NO SE APLICA FILTRO - limited={$hasLimitedAccess}, puntos_ids=" . json_encode($puntosIds) . PHP_EOL;
}
