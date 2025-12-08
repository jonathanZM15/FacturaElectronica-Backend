<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Buscar un usuario gerente
$gerente = App\Models\User::where('role', 'gerente')->first();

if ($gerente) {
    echo "=== Información del Gerente ===" . PHP_EOL;
    echo "ID: " . $gerente->id . PHP_EOL;
    echo "Nombre: " . $gerente->nombres . " " . $gerente->apellidos . PHP_EOL;
    echo "emisor_id: " . ($gerente->emisor_id ?? 'NULL') . PHP_EOL;
    echo "distribuidor_id: " . ($gerente->distribuidor_id ?? 'NULL') . PHP_EOL;
    $gerenteEstIds = $gerente->establecimientos_ids;
    if (is_string($gerenteEstIds)) {
        $gerenteEstIds = json_decode($gerenteEstIds, true);
    }
    echo "establecimientos_ids (parsed): " . json_encode($gerenteEstIds) . PHP_EOL;
    echo "created_by_id: " . ($gerente->created_by_id ?? 'NULL') . PHP_EOL;
    
    // Buscar cajeros con el mismo emisor_id
    $cajeros = App\Models\User::where('emisor_id', $gerente->emisor_id)->where('role', 'cajero')->get();
    echo PHP_EOL . "=== Cajeros del mismo emisor ===" . PHP_EOL;
    foreach ($cajeros as $cajero) {
        $cajeroEstIds = $cajero->establecimientos_ids;
        if (is_string($cajeroEstIds)) {
            $cajeroEstIds = json_decode($cajeroEstIds, true);
        }
        echo "  - Cajero ID: " . $cajero->id . ", Nombre: " . $cajero->nombres . PHP_EOL;
        echo "    emisor_id: " . $cajero->emisor_id . PHP_EOL;
        echo "    establecimientos_ids: " . json_encode($cajeroEstIds) . PHP_EOL;
        echo "    created_by_id: " . ($cajero->created_by_id ?? 'NULL') . PHP_EOL;
        
        // Verificar intersección
        $intersection = array_intersect($cajeroEstIds ?? [], $gerenteEstIds ?? []);
        echo "    Intersección con gerente: " . json_encode($intersection) . PHP_EOL;
    }
    
    // Buscar todos los usuarios del mismo emisor
    if ($gerente->emisor_id) {
        $usersDelEmisor = App\Models\User::where('emisor_id', $gerente->emisor_id)->get();
        echo PHP_EOL . "=== Todos los usuarios con emisor_id=" . $gerente->emisor_id . " ===" . PHP_EOL;
        foreach ($usersDelEmisor as $u) {
            echo "  - User ID: " . $u->id . ", Nombre: " . $u->nombres . ", Role: " . $u->role->value . PHP_EOL;
        }
    }
} else {
    echo "No hay gerentes en la base de datos" . PHP_EOL;
}

// Mostrar también info sobre la Company (emisor)
echo PHP_EOL . "=== Companies (Emisores) ===" . PHP_EOL;
$companies = App\Models\Company::all();
foreach ($companies as $c) {
    echo "Company ID: " . $c->id . ", RUC: " . $c->ruc . ", Nombre: " . $c->razon_social . ", created_by: " . $c->created_by . PHP_EOL;
}
