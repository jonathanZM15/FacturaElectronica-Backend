<?php

namespace App\Exceptions;

use Exception;

class InvalidWarehouseOperationException extends Exception
{
    // Se lanza cuando se intenta realizar una operación no permitida para el tipo de bodega
}
