<?php

namespace App\Services;

class FacturaCalculatorService
{
    /**
     * Calcula totales, detalles y agrupacion de impuestos para un comprobante.
     * Usa PHP_ROUND_HALF_UP por exigencia del SRI.
     *
     * @param array $detalles
     * @return array{detalles: array, impuestos: array, totales: array}
     */
    public function calcularComprobante(array $detalles): array
    {
        $detallesCalculados = [];
        $impuestosAgrupados = [];

        $totalDescuento = 0.0;
        $subtotalSinImpuestos = 0.0;

        foreach ($detalles as $index => $detalle) {
            $cantidad = $this->toFloat($detalle['cantidad'] ?? 0);
            $precioUnitario = $this->toFloat($detalle['precio_unitario'] ?? 0);
            $descuento = $this->toFloat($detalle['descuento'] ?? 0);

            $lineaBase = ($cantidad * $precioUnitario) - $descuento;

            // Redondeo por linea a 2 decimales (Half-Up) segun SRI
            $precioTotalSinImpuesto = round($lineaBase, 2, PHP_ROUND_HALF_UP);

            $totalDescuento += $descuento;
            $subtotalSinImpuestos += $precioTotalSinImpuesto;

            $detalleCalculado = $detalle;
            $detalleCalculado['precio_total_sin_impuesto'] = $precioTotalSinImpuesto;
            $detallesCalculados[] = $detalleCalculado;

            $impuestoInfo = $detalle['impuesto'] ?? null;
            if ($impuestoInfo) {
                $grupoKey = $this->buildImpuestoKey($impuestoInfo);
                if (!isset($impuestosAgrupados[$grupoKey])) {
                    $impuestosAgrupados[$grupoKey] = [
                        'tipo_impuesto_id' => $impuestoInfo['tipo_impuesto_id'] ?? null,
                        'codigo' => $impuestoInfo['codigo'] ?? null,
                        'tipo' => $impuestoInfo['tipo'] ?? null,
                        'tarifa' => $this->toFloat($impuestoInfo['tarifa'] ?? 0),
                        'base_imponible' => 0.0,
                        'valor' => 0.0,
                    ];
                }
                $impuestosAgrupados[$grupoKey]['base_imponible'] += $precioTotalSinImpuesto;
            }
        }

        $totalIva = 0.0;
        foreach ($impuestosAgrupados as $key => $grupo) {
            $base = $grupo['base_imponible'];
            $tarifa = $this->toFloat($grupo['tarifa']);

            // Calculo del impuesto por base agrupada, con Half-Up
            $valor = round($base * ($tarifa / 100), 2, PHP_ROUND_HALF_UP);

            $impuestosAgrupados[$key]['base_imponible'] = round($base, 2, PHP_ROUND_HALF_UP);
            $impuestosAgrupados[$key]['valor'] = $valor;

            $totalIva += $valor;
        }

        $subtotalSinImpuestos = round($subtotalSinImpuestos, 2, PHP_ROUND_HALF_UP);
        $totalDescuento = round($totalDescuento, 2, PHP_ROUND_HALF_UP);
        $totalIva = round($totalIva, 2, PHP_ROUND_HALF_UP);
        $importeTotal = round($subtotalSinImpuestos + $totalIva, 2, PHP_ROUND_HALF_UP);

        return [
            'detalles' => $detallesCalculados,
            'impuestos' => array_values($impuestosAgrupados),
            'totales' => [
                'subtotal_sin_impuestos' => $subtotalSinImpuestos,
                'total_descuento' => $totalDescuento,
                'total_iva' => $totalIva,
                'importe_total' => $importeTotal,
            ],
        ];
    }

    private function buildImpuestoKey(array $impuestoInfo): string
    {
        $tipo = (string) ($impuestoInfo['tipo'] ?? '');
        $codigo = (string) ($impuestoInfo['codigo'] ?? '');
        $tarifa = $this->toFloat($impuestoInfo['tarifa'] ?? 0);
        return $tipo . '|' . $codigo . '|' . $tarifa;
    }

    private function toFloat($value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }
        return (float) $value;
    }
}
