<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';

$container = $app->getContainer();
$hasher = $container->get('hash');

$password = 'Password123!@#';
$hash = $hasher->make($password);

echo "Password: $password\n";
echo "Hash: $hash\n";

// Verify
$verify = $hasher->check($password, $hash);
echo "Verify: " . ($verify ? 'YES' : 'NO') . "\n";
?>
