<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::orderByDesc('id')->first();
if (!$user) {
    echo "No users\n";
    exit;
}

$creador = $user->creador;
$creatorFull = $creador ? $creador->only(['id','username','email','role','nombres','apellidos','cedula']) : null;
var_export([
    'id' => $user->id,
    'username' => $user->username,
    'created_by_id' => $user->created_by_id,
    'creador' => $creatorFull,
]);
echo "\n";
