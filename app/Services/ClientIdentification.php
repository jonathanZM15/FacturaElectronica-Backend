<?php

namespace App\Services;

class ClientIdentification
{
    public const CODES = ['04' => 'RUC', '05' => 'CEDULA', '06' => 'PASAPORTE', '07' => 'CONSUMIDOR_FINAL', '08' => 'IDENTIFICACION_EXTERIOR', '09' => 'PLACA'];

    public static function normalize(string $type): string
    {
        $type = strtoupper(trim($type));
        return self::CODES[$type] ?? $type;
    }

    public static function valid(string $type, string $id): bool
    {
        return match ($type) {
            'RUC' => EcuadorIdentificationValidator::validateRuc($id),
            'CEDULA' => EcuadorIdentificationValidator::validateCedula($id),
            'CONSUMIDOR_FINAL' => $id === '9999999999999',
            'PASAPORTE', 'IDENTIFICACION_EXTERIOR', 'PLACA' => trim($id) !== '' && strlen($id) <= 13,
            default => false,
        };
    }
}
