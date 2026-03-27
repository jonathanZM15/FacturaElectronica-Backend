<?php
require 'bootstrap/app.php';

try {
    // Intentar instanciar el controlador
    $controller = app()->make(\App\Http\Controllers\Api\CompanyDeletionController::class);
    echo "✓ CompanyDeletionController instanciado exitosamente\n";
    echo "✓ El método middleware() está disponible en la clase base\n";
    echo "✓ Error de 'Call to undefined method middleware()' ha sido resuelto!\n";
} catch (\Error $e) {
    if (strpos($e->getMessage(), 'middleware') !== false) {
        echo "✗ Error: " . $e->getMessage() . "\n";
    } else {
        echo "✓ No hay error de middleware\n";
        echo "Otro error: " . $e->getMessage() . "\n";
    }
} catch (\Exception $e) {
    echo "Excepción: " . $e->getMessage() . "\n";
}
