<?php

namespace App\Services;

class EcuadorIdentificationValidator
{
    private const PROVINCE_MIN = 1;
    private const PROVINCE_MAX = 24;
    private const PROVINCE_FOREIGN = 30;

    public static function validateCedula(string $cedula): bool
    {
        $cedula = self::onlyDigits($cedula);
        if (strlen($cedula) !== 10) {
            return false;
        }
        if (!self::validProvince($cedula)) {
            return false;
        }
        if ((int) $cedula[2] >= 6) {
            return false;
        }

        $coeff = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $v = (int) $cedula[$i] * $coeff[$i];
            if ($v >= 10) {
                $v -= 9;
            }
            $sum += $v;
        }
        $mod = $sum % 10;
        $check = $mod === 0 ? 0 : 10 - $mod;

        return $check === (int) $cedula[9];
    }

    public static function validateRucPersonaNatural(string $ruc): bool
    {
        $ruc = self::onlyDigits($ruc);
        if (strlen($ruc) !== 13) {
            return false;
        }
        if (!self::validateCedula(substr($ruc, 0, 10))) {
            return false;
        }

        return substr($ruc, 10, 3) === '001';
    }

    public static function validateRucSociedadPrivada(string $ruc): bool
    {
        $ruc = self::onlyDigits($ruc);
        if (strlen($ruc) !== 13) {
            return false;
        }
        if (!self::validProvince($ruc)) {
            return false;
        }
        if ($ruc[2] !== '9') {
            return false;
        }
        if (substr($ruc, 10, 3) !== '001') {
            return false;
        }

        $coeff = [4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $ruc[$i] * $coeff[$i];
        }

        $check = self::mod11CheckDigit($sum);
        if ($check === null) {
            return false;
        }

        return $check === (int) $ruc[9];
    }

    public static function validateRucSociedadPublica(string $ruc): bool
    {
        $ruc = self::onlyDigits($ruc);
        if (strlen($ruc) !== 13) {
            return false;
        }
        if (!self::validProvince($ruc)) {
            return false;
        }
        if ($ruc[2] !== '6') {
            return false;
        }
        if (substr($ruc, 9, 4) !== '0001') {
            return false;
        }

        $coeff = [3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 8; $i++) {
            $sum += (int) $ruc[$i] * $coeff[$i];
        }

        $check = self::mod11CheckDigit($sum);
        if ($check === null) {
            return false;
        }

        return $check === (int) $ruc[8];
    }

    public static function validateRuc(string $ruc): bool
    {
        $ruc = self::onlyDigits($ruc);
        if (strlen($ruc) !== 13) {
            return false;
        }

        $third = (int) $ruc[2];
        if ($third >= 0 && $third <= 5) {
            return self::validateRucPersonaNatural($ruc);
        }
        if ($third === 6) {
            return self::validateRucSociedadPublica($ruc);
        }
        if ($third === 9) {
            return self::validateRucSociedadPrivada($ruc);
        }

        return false;
    }

    private static function onlyDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }

    private static function validProvince(string $value): bool
    {
        $province = (int) substr($value, 0, 2);
        return ($province >= self::PROVINCE_MIN && $province <= self::PROVINCE_MAX)
            || $province === self::PROVINCE_FOREIGN;
    }

    private static function mod11CheckDigit(int $sum): ?int
    {
        $mod = $sum % 11;
        $check = 11 - $mod;
        if ($check === 11) {
            return 0;
        }
        if ($check === 10) {
            return null;
        }
        return $check;
    }
}
