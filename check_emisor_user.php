<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Buscar usuario emisor por cédula
$user = App\Models\User::where('cedula', '0612345678')->first();

if ($user) {
    echo "=== Usuario Emisor ===" . PHP_EOL;
    echo "ID: " . $user->id . PHP_EOL;
    echo "Nombre: " . $user->nombres . " " . $user->apellidos . PHP_EOL;
    echo "Role: " . $user->role->value . PHP_EOL;
    echo "emisor_id: " . $user->emisor_id . PHP_EOL;
    echo PHP_EOL;
    
    echo "=== Datos crudos ===" . PHP_EOL;
    echo "establecimientos_ids (raw): " . var_export($user->getAttributes()['establecimientos_ids'] ?? null, true) . PHP_EOL;
    echo "puntos_emision_ids (raw): " . var_export($user->getAttributes()['puntos_emision_ids'] ?? null, true) . PHP_EOL;
    echo PHP_EOL;
    
    echo "=== Atributos computados ===" . PHP_EOL;
    echo "establecimientos: " . json_encode($user->establecimientos) . PHP_EOL;
    echo "puntos_emision: " . json_encode($user->puntos_emision) . PHP_EOL;
} else {
    echo "Usuario no encontrado" . PHP_EOL;
}

// Mostrar todos los usuarios con sus asignaciones
echo PHP_EOL . "=== Todos los usuarios del emisor 1 ===" . PHP_EOL;
$users = App\Models\User::where('emisor_id', 1)->get();
foreach ($users as $u) {
    $estIds = $u->getAttributes()['establecimientos_ids'] ?? 'null';
    $puntosIds = $u->getAttributes()['puntos_emision_ids'] ?? 'null';
    echo "- {$u->cedula} ({$u->role->value}): est_ids={$estIds}, puntos_ids={$puntosIds}" . PHP_EOL;
}
