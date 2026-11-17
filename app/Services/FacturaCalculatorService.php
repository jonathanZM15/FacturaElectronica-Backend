<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class FacturaCalculatorService
{
    /**
     * Calcula totales, detalles y agrupacion de impuestos para un comprobante.
     * Aritmética decimal exacta: base por línea e impuesto por base agrupada,
     * con redondeo HalfUp a centavos, igual que el carrito del POS.
     *
     * @param array $detalles
     * @return array{detalles: array, impuestos: array, totales: array}
     */
    public function calcularComprobante(array $detalles): array
    {
        $detallesCalculados = [];
        $impuestosAgrupados = [];

        $totalDescuento = BigDecimal::zero();
        $subtotalSinImpuestos = BigDecimal::zero();

        foreach ($detalles as $index => $detalle) {
            $cantidad = $this->toDecimal($detalle['cantidad'] ?? 0);
            $precioUnitario = $this->toDecimal($detalle['precio_unitario'] ?? 0);
            $descuento = $this->toDecimal($detalle['descuento'] ?? 0);
            $baseBruta = $cantidad->multipliedBy($precioUnitario);

            if ($cantidad->isLessThanOrEqualTo(0) || $precioUnitario->isNegative() || $descuento->isNegative() || $descuento->isGreaterThan($baseBruta)) {
                throw \Illuminate\Validation\ValidationException::withMessages(["detalles.$index" => ['Cantidad, precio o descuento inválidos. El descuento no puede superar la base.']]);
            }

            $lineaBase = $baseBruta->minus($descuento);

            // Redondeo por linea a 2 decimales (Half-Up) segun SRI
            $precioTotalSinImpuesto = $lineaBase->toScale(2, RoundingMode::HalfUp);

            $totalDescuento = $totalDescuento->plus((string) $descuento);
            $subtotalSinImpuestos = $subtotalSinImpuestos->plus($precioTotalSinImpuesto);

            $detalleCalculado = $detalle;
            $detalleCalculado['precio_total_sin_impuesto'] = $precioTotalSinImpuesto->toFloat();
            $detallesCalculados[] = $detalleCalculado;

            $impuestoInfo = $detalle['impuesto'] ?? null;
            if ($impuestoInfo) {
                $grupoKey = $this->buildImpuestoKey($impuestoInfo);
                if (!isset($impuestosAgrupados[$grupoKey])) {
                    $impuestosAgrupados[$grupoKey] = [
                        'tipo_impuesto_id' => $impuestoInfo['tipo_impuesto_id'] ?? null,
                        'codigo' => $impuestoInfo['codigo'] ?? null,
                        'codigo_impuesto' => $impuestoInfo['codigo_impuesto'] ?? 2,
                        'codigo_porcentaje' => $impuestoInfo['codigo_porcentaje'] ?? $impuestoInfo['codigo'] ?? null,
                        'tipo' => $impuestoInfo['tipo'] ?? null,
                        'tarifa' => $this->toFloat($impuestoInfo['tarifa'] ?? 0),
                        'base_imponible' => BigDecimal::zero(),
                        'valor' => 0.0,
                    ];
                }
                $impuestosAgrupados[$grupoKey]['base_imponible'] = $impuestosAgrupados[$grupoKey]['base_imponible']->plus($precioTotalSinImpuesto);
            }
        }

        $totalIva = BigDecimal::zero();
        foreach ($impuestosAgrupados as $key => $grupo) {
            $base = $grupo['base_imponible'];
            $tarifa = $this->toFloat($grupo['tarifa']);

            // Calculo del impuesto por base agrupada, con Half-Up
            $valor = $base->multipliedBy((string) $tarifa)->dividedBy(100, 2, RoundingMode::HalfUp);

            $impuestosAgrupados[$key]['base_imponible'] = $base->toFloat();
            $impuestosAgrupados[$key]['valor'] = $valor->toFloat();

            $totalIva = $totalIva->plus($valor);
        }

        $importeTotal = $subtotalSinImpuestos->plus($totalIva);
        if ($importeTotal->isGreaterThan('999999999999.99')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['detalles' => ['El importe total excede el límite admitido por el sistema.']]);
        }

        return [
            'detalles' => $detallesCalculados,
            'impuestos' => array_values($impuestosAgrupados),
            'totales' => [
                'subtotal_sin_impuestos' => $subtotalSinImpuestos->toFloat(),
                'total_descuento' => $totalDescuento->toScale(2, RoundingMode::HalfUp)->toFloat(),
                'total_iva' => $totalIva->toFloat(),
                'importe_total' => $importeTotal->toFloat(),
            ],
        ];
    }

    private function buildImpuestoKey(array $impuestoInfo): string
    {
        $tipo = (string) ($impuestoInfo['tipo'] ?? '');
        $codigo = (string) ($impuestoInfo['codigo'] ?? '');
        $tarifa = $this->toFloat($impuestoInfo['tarifa'] ?? 0);
        return ($impuestoInfo['tipo_impuesto_id'] ?? '') . '|' . $tipo . '|' . $codigo . '|' . $tarifa;
    }

    private function toFloat($value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }
        return (float) $value;
    }

    private function toDecimal(mixed $value): BigDecimal
    {
        if (is_string($value)) $value = str_replace(',', '.', $value);
        if (!\App\Rules\DecimalValue::accepts($value, 6, '999999999999.999999')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['detalles' => ['Importe o cantidad fuera del rango o precisión admitidos.']]);
        }
        return BigDecimal::of((string) $value);
    }
}
