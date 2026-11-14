<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** Reads both historical double-encoded JSON and canonical arrays, failing closed. */
class IdList implements CastsAttributes
{
    public static function normalize(mixed $value): array
    {
        for ($i = 0; is_string($value) && $i < 3; $i++) {
            $value = json_decode($value, true);
        }
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }
        $ids = [];
        foreach ($value as $id) {
            if (!(is_int($id) || (is_string($id) && ctype_digit($id))) || (int) $id < 1) {
                return [];
            }
            $ids[] = (int) $id;
        }
        return array_values(array_unique($ids));
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return self::normalize($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return json_encode(self::normalize($value), JSON_THROW_ON_ERROR);
    }
}
