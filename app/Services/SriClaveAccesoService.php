<?php

namespace App\Services;

class SriClaveAccesoService
{
    public function generarFactura(
        string $fechaEmision,
        string $ruc,
        string $ambiente,
        string $serie,
        string $secuencial,
        ?string $codigoNumerico = null,
        string $tipoComprobante = '01',
        string $tipoEmision = '1'
    ): string {
        $codigoNumerico = $codigoNumerico ?: $this->generarCodigoNumerico();

        $base = $fechaEmision
            . $tipoComprobante
            . preg_replace('/\D+/', '', $ruc)
            . $this->normalizarAmbiente($ambiente)
            . $this->normalizarSerie($serie)
            . $this->normalizarSecuencial($secuencial)
            . $this->normalizarCodigoNumerico($codigoNumerico)
            . $this->normalizarTipoEmision($tipoEmision);

        return $base . $this->digitoVerificadorModulo11($base);
    }

    public function digitoVerificadorModulo11(string $base): string
    {
        $factor = 2;
        $suma = 0;

        for ($i = strlen($base) - 1; $i >= 0; $i--) {
            $suma += (int) $base[$i] * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }

        $mod = $suma % 11;
        $digito = 11 - $mod;

        if ($digito === 11) {
            return '0';
        }

        if ($digito === 10) {
            return '1';
        }

        return (string) $digito;
    }

    public function generarCodigoNumerico(): string
    {
        return str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
    }

    public function normalizarSerie(string $serie): string
    {
        return str_pad(preg_replace('/\D+/', '', $serie), 6, '0', STR_PAD_LEFT);
    }

    public function normalizarSecuencial(string $secuencial): string
    {
        return str_pad(preg_replace('/\D+/', '', $secuencial), 9, '0', STR_PAD_LEFT);
    }

    public function normalizarCodigoNumerico(string $codigoNumerico): string
    {
        return str_pad(preg_replace('/\D+/', '', $codigoNumerico), 8, '0', STR_PAD_LEFT);
    }

    public function normalizarAmbiente(string $ambiente): string
    {
        return strtoupper($ambiente) === 'PRUEBAS' ? '1' : '2';
    }

    public function normalizarTipoEmision(string $tipoEmision): string
    {
        return preg_replace('/\D+/', '', $tipoEmision) ?: '1';
    }
}