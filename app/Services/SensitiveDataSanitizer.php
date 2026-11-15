<?php

namespace App\Services;

class SensitiveDataSanitizer
{
    public static function clean(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 15) return '[contenido anidado omitido]';
        if (is_object($value)) $value = get_object_vars($value);
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $normalized = preg_replace('/[^a-z0-9ñ]/u', '', mb_strtolower((string) $key));
                $sensitive = str_ends_with($normalized, 'password') || in_array($normalized, [
                    'passphrase', 'privatekey', 'clave', 'contrasena', 'contraseña',
                    'token', 'authtoken', 'authorization', 'accesstoken', 'refreshtoken',
                ], true);
                $result[$key] = $sensitive ? '********' : self::clean($item, $depth + 1);
            }
            return $result;
        }
        if (!is_string($value)) return $value;

        $json = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return json_encode(self::clean($json, $depth + 1), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $value = preg_replace('~-----BEGIN (?:ENCRYPTED |RSA |EC )?PRIVATE KEY-----.*?-----END (?:ENCRYPTED |RSA |EC )?PRIVATE KEY-----~s', '[clave privada omitida]', $value);
        $value = preg_replace('~<((?:[A-Za-z0-9_-]+:)?(?:password|contrasena|contraseña|private_key|clave))\b[^>]*>.*?</\1\s*>~isu', '<$1>********</$1>', $value);
        $pattern = <<<'REGEX'
~(password|passphrase|contrasena|contraseña|private_key|cert_password|encrypted_password|clave)(["']?\s*[:=]\s*)(?:"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|[^&<\r\n]+)~iu
REGEX;
        return preg_replace($pattern, '$1$2********', $value);
    }
}
