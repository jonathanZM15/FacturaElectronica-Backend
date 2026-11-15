<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Evita truncamiento y bytes nulos al guardar contraseñas con bcrypt. */
class PasswordStorageLimit implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
            $fail('La contraseña supera el límite de 72 bytes o contiene un carácter nulo no permitido.');
        }
    }
}
