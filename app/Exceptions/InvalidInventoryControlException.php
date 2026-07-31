<?php

namespace App\Exceptions;

use Exception;

class InvalidInventoryControlException extends Exception
{
    // Se lanza cuando se intentan aplicar reglas de inventario inválidas (ej. stock a un servicio)
}
