<?php

namespace App\Services;

class ClientIdentification
{
    public const CODES = [
        '04' => 'RUC',
        '05' => 'CEDULA',
        '06' => 'PASAPORTE',
        '07' => 'CONSUMIDOR_FINAL',
        '08' => 'IDENTIFICACION_EXTERIOR',
        '09' => 'PLACA',
    ];

    public const FACTURA_CODES = [
        '04' => 'RUC',
        '05' => 'CEDULA',
        '06' => 'PASAPORTE',
        '07' => 'CONSUMIDOR_FINAL',
        '08' => 'IDENTIFICACION_EXTERIOR',
    ];

    public const TYPE_TO_SRI = [
        'RUC' => '04',
        'CEDULA' => '05',
        'PASAPORTE' => '06',
        'CONSUMIDOR_FINAL' => '07',
        'IDENTIFICACION_EXTERIOR' => '08',
        'EXTERIOR' => '08',
        'PLACA' => '09',
    ];

    public static function normalize(string $type): string
    {
        $t = strtoupper(trim($type));
        if (isset(self::CODES[$t])) {
            return self::CODES[$t];
        }
        if ($t === 'EXTERIOR') {
            return 'IDENTIFICACION_EXTERIOR';
        }
        return $t;
    }

    public static function toSriCode(string $type): ?string
    {
        $t = strtoupper(trim($type));
        if (isset(self::CODES[$t])) {
            return $t;
        }
        $normalized = self::normalize($t);
        return self::TYPE_TO_SRI[$normalized] ?? null;
    }

    public static function toSriCodeForFactura(string $type): ?string
    {
        $code = self::toSriCode($type);
        if ($code === null || $code === '09') {
            return null;
        }
        return $code;
    }

    public static function valid(string $type, string $id): bool
    {
        $normalized = self::normalize($type);
        $id = trim($id);
        return match ($normalized) {
            'RUC' => EcuadorIdentificationValidator::validateRuc($id),
            'CEDULA' => preg_match('/^[0-9]{10}$/D', $id) === 1 && EcuadorIdentificationValidator::validateCedula($id),
            'CONSUMIDOR_FINAL' => $id === '9999999999999',
            'PASAPORTE', 'IDENTIFICACION_EXTERIOR' => $id !== '' && strlen($id) <= 20 && preg_match('/^[A-Za-z0-9\-\._]+$/', $id) === 1,
            default => false,
        };
    }
}
