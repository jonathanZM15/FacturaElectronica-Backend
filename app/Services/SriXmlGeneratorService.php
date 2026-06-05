<?php

namespace App\Services;

use App\Models\Comprobante;
use DOMDocument;

class SriXmlGeneratorService
{
    public function generarXmlFactura(Comprobante $comprobante): array
    {
        $company = $comprobante->company;
        $cliente = $comprobante->cliente;
        $establecimiento = $comprobante->establecimiento;

        $fechaEmision = $this->formatFechaEmision($comprobante->fecha_emision);
        $fechaClave = $this->formatFechaClave($comprobante->fecha_emision);
        $tipoComprobante = '01';
        $ruc = (string) ($company->ruc ?? '');
        $ambiente = $this->mapAmbiente($comprobante->ambiente ?? $company->ambiente ?? 'PRODUCCION');
        $serie = $this->buildSerie($comprobante);
        $secuencial = $this->padLeft((string) $comprobante->secuencial, 9);
        $codigoNumerico = $this->padLeft((string) ($comprobante->secuencial ?? random_int(1, 99999999)), 8);
        $tipoEmision = '1';

        $claveAcceso = $this->generarClaveAcceso(
            $fechaClave,
            $tipoComprobante,
            $ruc,
            $ambiente,
            $serie,
            $secuencial,
            $codigoNumerico,
            $tipoEmision
        );

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $factura = $dom->createElement('factura');
        $factura->setAttribute('id', 'comprobante');
        $factura->setAttribute('version', '2.1.0');
        $dom->appendChild($factura);

        $infoTributaria = $dom->createElement('infoTributaria');
        $factura->appendChild($infoTributaria);

        $this->appendText($dom, $infoTributaria, 'ambiente', $ambiente);
        $this->appendText($dom, $infoTributaria, 'tipoEmision', $tipoEmision);
        $this->appendText($dom, $infoTributaria, 'razonSocial', $company->razon_social ?? '');
        $this->appendText($dom, $infoTributaria, 'nombreComercial', $company->nombre_comercial ?? $company->razon_social ?? '');
        $this->appendText($dom, $infoTributaria, 'ruc', $ruc);
        $this->appendText($dom, $infoTributaria, 'claveAcceso', $claveAcceso);
        $this->appendText($dom, $infoTributaria, 'codDoc', $tipoComprobante);
        $this->appendText($dom, $infoTributaria, 'estab', $comprobante->codigo_establecimiento ?? substr($serie, 0, 3));
        $this->appendText($dom, $infoTributaria, 'ptoEmi', $comprobante->punto_emision_codigo ?? substr($serie, 3, 3));
        $this->appendText($dom, $infoTributaria, 'secuencial', $secuencial);
        $this->appendText($dom, $infoTributaria, 'dirMatriz', $company->direccion_matriz ?? '');

        if (($company->agente_retencion ?? 'NO') === 'SI') {
            $this->appendText($dom, $infoTributaria, 'agenteRetencion', $company->numero_resolucion_agente_retencion ?? '');
        }

        $contribuyenteRimpe = $this->buildRimpe($company->regimen_tributario ?? '');
        if ($contribuyenteRimpe) {
            $this->appendText($dom, $infoTributaria, 'contribuyenteRimpe', $contribuyenteRimpe);
        }

        $infoFactura = $dom->createElement('infoFactura');
        $factura->appendChild($infoFactura);

        $this->appendText($dom, $infoFactura, 'fechaEmision', $fechaEmision);
        $this->appendText($dom, $infoFactura, 'dirEstablecimiento', $establecimiento->direccion ?? $company->direccion_matriz ?? '');
        $this->appendText($dom, $infoFactura, 'obligadoContabilidad', $company->obligado_contabilidad ?? 'NO');
        $this->appendText($dom, $infoFactura, 'tipoIdentificacionComprador', $this->mapTipoIdentificacion($cliente));
        $this->appendText($dom, $infoFactura, 'razonSocialComprador', $cliente->razon_social ?? 'CONSUMIDOR FINAL');
        $this->appendText($dom, $infoFactura, 'identificacionComprador', $cliente->identificacion ?? '9999999999999');
        $this->appendText($dom, $infoFactura, 'direccionComprador', $cliente->direccion ?? '');
        $this->appendText($dom, $infoFactura, 'totalSinImpuestos', $this->formatMoney($comprobante->subtotal_sin_impuestos ?? 0));
        $this->appendText($dom, $infoFactura, 'totalDescuento', $this->formatMoney($comprobante->total_descuento ?? 0));

        $totalConImpuestos = $dom->createElement('totalConImpuestos');
        $infoFactura->appendChild($totalConImpuestos);

        foreach ($comprobante->impuestos ?? [] as $impuesto) {
            $totalImpuesto = $dom->createElement('totalImpuesto');
            $this->appendText($dom, $totalImpuesto, 'codigo', $this->mapCodigoImpuesto($impuesto));
            $this->appendText($dom, $totalImpuesto, 'codigoPorcentaje', $this->mapCodigoPorcentaje($impuesto));
            $this->appendText($dom, $totalImpuesto, 'baseImponible', $this->formatMoney($impuesto->base_imponible ?? 0));
            $this->appendText($dom, $totalImpuesto, 'tarifa', $this->formatMoney($impuesto->tarifa ?? 0));
            $this->appendText($dom, $totalImpuesto, 'valor', $this->formatMoney($impuesto->valor ?? 0));
            $totalConImpuestos->appendChild($totalImpuesto);
        }

        $this->appendText($dom, $infoFactura, 'propina', $this->formatMoney($comprobante->propina ?? 0));
        $this->appendText($dom, $infoFactura, 'importeTotal', $this->formatMoney($comprobante->total ?? 0));
        $this->appendText($dom, $infoFactura, 'moneda', 'DOLAR');

        $pagos = $dom->createElement('pagos');
        $infoFactura->appendChild($pagos);
        $pago = $dom->createElement('pago');
        $this->appendText($dom, $pago, 'formaPago', '01');
        $this->appendText($dom, $pago, 'total', $this->formatMoney($comprobante->total ?? 0));
        $pagos->appendChild($pago);

        $detalles = $dom->createElement('detalles');
        $factura->appendChild($detalles);

        foreach ($comprobante->detalles ?? [] as $detalle) {
            $detalleNode = $dom->createElement('detalle');
            $this->appendText($dom, $detalleNode, 'codigoPrincipal', (string) ($detalle->producto_id ?? ''));
            $this->appendText($dom, $detalleNode, 'descripcion', $detalle->descripcion ?? '');
            $this->appendText($dom, $detalleNode, 'cantidad', $this->formatCantidad($detalle->cantidad ?? 0));
            $this->appendText($dom, $detalleNode, 'precioUnitario', $this->formatCantidad($detalle->precio_unitario ?? 0));
            $this->appendText($dom, $detalleNode, 'descuento', $this->formatMoney($detalle->descuento ?? 0));
            $this->appendText($dom, $detalleNode, 'precioTotalSinImpuesto', $this->formatMoney($detalle->subtotal ?? 0));

            $impuestosNode = $dom->createElement('impuestos');
            foreach ($detalle->impuestos ?? [] as $imp) {
                $impuestoNode = $dom->createElement('impuesto');
                $this->appendText($dom, $impuestoNode, 'codigo', $this->mapCodigoImpuesto($imp));
                $this->appendText($dom, $impuestoNode, 'codigoPorcentaje', $this->mapCodigoPorcentaje($imp));
                $this->appendText($dom, $impuestoNode, 'tarifa', $this->formatMoney($imp->tarifa ?? 0));
                $this->appendText($dom, $impuestoNode, 'baseImponible', $this->formatMoney($imp->base_imponible ?? 0));
                $this->appendText($dom, $impuestoNode, 'valor', $this->formatMoney($imp->valor ?? 0));
                $impuestosNode->appendChild($impuestoNode);
            }
            $detalleNode->appendChild($impuestosNode);

            $detalles->appendChild($detalleNode);
        }

        if (!empty($cliente->email ?? null)) {
            $infoAdicional = $dom->createElement('infoAdicional');
            $factura->appendChild($infoAdicional);
            $campoAdicional = $dom->createElement('campoAdicional', $cliente->email);
            $campoAdicional->setAttribute('nombre', 'Email');
            $infoAdicional->appendChild($campoAdicional);
        }

        return [
            'xml' => $dom->saveXML(),
            'clave_acceso' => $claveAcceso,
        ];
    }

    private function generarClaveAcceso(
        string $fechaEmision,
        string $tipoComprobante,
        string $ruc,
        string $ambiente,
        string $serie,
        string $secuencial,
        string $codigoNumerico,
        string $tipoEmision
    ): string {
        $base = $fechaEmision
            . $tipoComprobante
            . $ruc
            . $ambiente
            . $serie
            . $secuencial
            . $codigoNumerico
            . $tipoEmision;

        $digito = $this->modulo11($base);
        return $base . $digito;
    }

    private function modulo11(string $base): string
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
            $digito = 0;
        } elseif ($digito === 10) {
            $digito = 1;
        }

        return (string) $digito;
    }

    private function appendText(DOMDocument $dom, $parent, string $name, string $value): void
    {
        $node = $dom->createElement($name);
        $node->appendChild($dom->createTextNode($value));
        $parent->appendChild($node);
    }

    private function buildSerie(Comprobante $comprobante): string
    {
        $estab = $this->padLeft((string) ($comprobante->codigo_establecimiento ?? ''), 3);
        $pto = $this->padLeft((string) ($comprobante->punto_emision_codigo ?? ''), 3);
        return $estab . $pto;
    }

    private function padLeft(string $value, int $length): string
    {
        return str_pad($value, $length, '0', STR_PAD_LEFT);
    }

    private function formatFechaEmision($fecha): string
    {
        $date = $this->normalizeDate($fecha);
        return $date ? $date->format('d/m/Y') : '';
    }

    private function formatFechaClave($fecha): string
    {
        $date = $this->normalizeDate($fecha);
        return $date ? $date->format('dmY') : '';
    }

    private function normalizeDate($fecha): ?\DateTimeInterface
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha;
        }
        if (is_string($fecha) && $fecha !== '') {
            try {
                return new \DateTime($fecha);
            } catch (\Exception $_) {
                return null;
            }
        }
        return null;
    }

    private function mapAmbiente(string $ambiente): string
    {
        return strtoupper($ambiente) === 'PRUEBAS' ? '1' : '2';
    }

    private function mapTipoIdentificacion($cliente): string
    {
        $tipo = strtoupper((string) ($cliente->tipo_identificacion ?? ''));
        if ($tipo === 'RUC') {
            return '04';
        }
        if ($tipo === 'CEDULA') {
            return '05';
        }
        if ($tipo === 'EXTERIOR') {
            return '08';
        }
        if ($tipo === 'CONSUMIDOR_FINAL') {
            return '07';
        }

        $id = (string) ($cliente->identificacion ?? '');
        if (strlen($id) === 13) {
            return '04';
        }
        if (strlen($id) === 10) {
            return '05';
        }
        return '07';
    }

    private function buildRimpe(string $regimen): ?string
    {
        $regimen = strtoupper($regimen);
        if (str_starts_with($regimen, 'RIMPE')) {
            return str_replace('_', ' ', $regimen);
        }
        return null;
    }

    private function formatMoney($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function formatCantidad($value): string
    {
        return number_format((float) $value, 6, '.', '');
    }

    private function mapCodigoPorcentaje($impuesto): string
    {
        $tipoImpuesto = $impuesto->tipoImpuesto ?? null;
        if (!empty($tipoImpuesto?->codigo_porcentaje)) {
            return (string) $tipoImpuesto->codigo_porcentaje;
        }

        if (!empty($impuesto->codigo_porcentaje)) {
            return (string) $impuesto->codigo_porcentaje;
        }

        if ($this->isNoObjetoIva($impuesto)) {
            return '6';
        }
        if ($this->isExentoIva($impuesto)) {
            return '7';
        }

        $tarifa = (float) ($impuesto->tarifa ?? 0);
        if (abs($tarifa - 0.0) < 0.00001) {
            return '0';
        }
        if (abs($tarifa - 12.0) < 0.00001) {
            return '2';
        }
        if (abs($tarifa - 14.0) < 0.00001) {
            return '3';
        }
        if (abs($tarifa - 15.0) < 0.00001) {
            return '4';
        }

        return (string) ((int) round($tarifa));
    }

    private function mapCodigoImpuesto($impuesto): string
    {
        $tipoImpuesto = $impuesto->tipoImpuesto ?? null;
        if (!empty($tipoImpuesto?->codigo_impuesto)) {
            return (string) $tipoImpuesto->codigo_impuesto;
        }

        if (!empty($impuesto->codigo_impuesto)) {
            return (string) $impuesto->codigo_impuesto;
        }

        return '2';
    }

    private function isNoObjetoIva($impuesto): bool
    {
        $tipo = $this->normalizeImpuestoTipo($impuesto);
        return str_contains($tipo, 'NO OBJETO') || str_contains($tipo, 'NO_OBJETO');
    }

    private function isExentoIva($impuesto): bool
    {
        $tipo = $this->normalizeImpuestoTipo($impuesto);
        return str_contains($tipo, 'EXENTO');
    }

    private function normalizeImpuestoTipo($impuesto): string
    {
        $tipo = (string) ($impuesto->tipoImpuesto->tipo_impuesto ?? $impuesto->tipo_impuesto ?? $impuesto->tipo ?? '');
        $nombre = (string) ($impuesto->tipoImpuesto->nombre ?? $impuesto->nombre ?? '');
        $merged = trim($tipo . ' ' . $nombre);
        return strtoupper($merged);
    }
}
