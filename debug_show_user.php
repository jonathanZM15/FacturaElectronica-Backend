<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();

$user = \App\Models\User::find(5);
if (!$user) { echo "User 5 not found\n"; exit; }

$attrs = $user->getAttributes();
$normalize = function($value) {
    if (is_array($value)) return $value;
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            if (is_array($decoded)) return $decoded;
        }
    }
    return [];
};

$puntos = $normalize($attrs['puntos_emision_ids'] ?? null);
$estabs = $normalize($attrs['establecimientos_ids'] ?? null);

echo json_encode([
    'id' => $user->id,
    'email' => $user->email,
    'username' => $user->username,
    'cedula' => $user->cedula,
    'role' => $user->role->value,
    'emisor_id' => $user->emisor_id,
    'establecimientos_ids' => $attrs['establecimientos_ids'] ?? null,
    'puntos_emision_ids' => $attrs['puntos_emision_ids'] ?? null,
    'establecimientos_ids_norm' => $estabs,
    'puntos_emision_ids_norm' => $puntos,
], JSON_PRETTY_PRINT) . "\n";
