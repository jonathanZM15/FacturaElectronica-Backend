<?php

namespace App\Rules;

use Brick\Math\BigDecimal;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Validate before SQL; never silently round a quantity or a payment. */
class DecimalValue implements ValidationRule
{
    public function __construct(private int $scale = 6, private string $max = '99999999.999999', private string $min = '0') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!self::accepts($value, $this->scale, $this->max, $this->min)) {
            $fail("El campo :attribute debe estar entre {$this->min} y {$this->max}, con máximo {$this->scale} decimales.");
        }
    }

    public static function accepts(mixed $value, int $scale = 6, string $max = '99999999.999999', string $min = '0'): bool
    {
        if ((!is_string($value) && !is_int($value) && !is_float($value)) || !is_numeric($value) || strlen((string) $value) > 100 || !is_finite((float) $value)) return false;
        // Bound exponent expansion before handing a string to arbitrary precision arithmetic.
        if (preg_match('/[eE]([+-]?\d+)$/', (string) $value, $m) && abs((float) $m[1]) > 30) return false;
        try {
            $number = BigDecimal::of((string) $value)->toScale($scale);
            return $number->isGreaterThanOrEqualTo($min) && $number->isLessThanOrEqualTo($max);
        } catch (\Throwable) {
            return false;
        }
    }
}
