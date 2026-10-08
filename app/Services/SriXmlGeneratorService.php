<?php

namespace App\Services;

use App\Models\Comprobante;
use DOMDocument;

class SriXmlGeneratorService
{

    public function generarXmlLiquidacionCompra(Comprobante $comprobante): array
    {
        $company = $comprobante->company;
        $proveedor = $comprobante->proveedor;
        $establecimiento = $comprobante->establecimiento;

        $fechaEmision = $this->formatFechaEmision($comprobante->fecha_emision);
        $fechaClave = $this->formatFechaClave($comprobante->fecha_emision);
        $tipoComprobante = '03';
        $ruc = (string) ($company->ruc ?? '');
        $ambiente = $this->mapAmbiente($comprobante->ambiente ?? $company->ambiente ?? 'PRODUCCION');
        $serie = $this->buildSerie($comprobante);
        $secuencial = $this->padLeft((string) $comprobante->secuencial, 9);
        $codigoNumerico = $this->padLeft((string) ($comprobante->secuencial ?? random_int(1, 99999999)), 8);
        $tipoEmision = '1';

        $claveAcceso = $comprobante->clave_acceso ?: (new SriClaveAccesoService())->generarFactura(
            $fechaClave,
            $ruc,
            $ambiente,
            $serie,
            $secuencial,
            $codigoNumerico,
            $tipoComprobante,
            $tipoEmision
        );

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $liquidacion = $dom->createElement('liquidacionCompra');
        $liquidacion->setAttribute('id', 'comprobante');
        $liquidacion->setAttribute('version', '1.1.0');
        $dom->appendChild($liquidacion);

        $infoTributaria = $dom->createElement('infoTributaria');
        $liquidacion->appendChild($infoTributaria);

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
            $this->appendText($dom, $infoTributaria, 'agenteRetencion', $company->numero_resolucion_agente_retencion ?? '1');
        }

        if (($company->contribuyente_especial ?? 'NO') === 'SI') {
            $this->appendText($dom, $infoTributaria, 'contribuyenteEspecial', $company->numero_resolucion_contribuyente_especial ?? '1');
        }

        $regimen = strtoupper((string) ($company->regimen_tributario ?? ''));
        if (str_contains($regimen, 'RIMPE') && !str_contains($regimen, 'NEGOCIO POPULAR')) {
            $contribuyenteRimpe = 'CONTRIBUYENTE RÉGIMEN RIMPE';
            $this->appendText($dom, $infoTributaria, 'contribuyenteRimpe', $contribuyenteRimpe);
        }

        $infoLiquidacion = $dom->createElement('infoLiquidacionCompra');
        $liquidacion->appendChild($infoLiquidacion);

        $this->appendText($dom, $infoLiquidacion, 'fechaEmision', $fechaEmision);
        $this->appendText($dom, $infoLiquidacion, 'dirEstablecimiento', $establecimiento->direccion ?? $company->direccion_matriz ?? '');
        $this->appendText($dom, $infoLiquidacion, 'obligadoContabilidad', $company->obligado_contabilidad ?? 'NO');
        $this->appendText($dom, $infoLiquidacion, 'tipoIdentificacionProveedor', $this->mapTipoIdentificacion($proveedor));
        $this->appendText($dom, $infoLiquidacion, 'razonSocialProveedor', $proveedor->razon_social ?? '');
        $this->appendText($dom, $infoLiquidacion, 'identificacionProveedor', $proveedor->identificacion ?? '');
        $this->appendText($dom, $infoLiquidacion, 'direccionProveedor', $proveedor->direccion ?? '');
        $this->appendText($dom, $infoLiquidacion, 'totalSinImpuestos', $this->formatMoney($comprobante->subtotal_sin_impuestos ?? 0));
        $this->appendText($dom, $infoLiquidacion, 'totalDescuento', $this->formatMoney($comprobante->total_descuento ?? 0));

        $totalConImpuestos = $dom->createElement('totalConImpuestos');
        $infoLiquidacion->appendChild($totalConImpuestos);

        $impuestosTotales = collect($comprobante->impuestos ?? [])
            ->filter(fn ($impuesto) => $impuesto->comprobante_detalle_id === null);

        if ($impuestosTotales->isEmpty()) {
            $impuestosTotales = collect($comprobante->impuestos ?? []);
        }

        $impuestosConsolidados = [];
        foreach ($impuestosTotales as $impuesto) {
            $codigo = $this->mapCodigoImpuesto($impuesto);
            $porcentaje = $this->mapCodigoPorcentaje($impuesto);
            $key = $codigo . '_' . $porcentaje;

            if (!isset($impuestosConsolidados[$key])) {
                $impuestosConsolidados[$key] = [
                    'codigo' => $codigo,
                    'codigoPorcentaje' => $porcentaje,
                    'tarifa' => (float) ($impuesto->tarifa ?? 0),
                    'baseImponible' => 0.0,
                    'valor' => 0.0
                ];
            }
            $impuestosConsolidados[$key]['baseImponible'] += (float) ($impuesto->base_imponible ?? 0);
            $impuestosConsolidados[$key]['valor'] += (float) ($impuesto->valor ?? 0);
        }

        foreach ($impuestosConsolidados as $imp) {
            $totalImpuesto = $dom->createElement('totalImpuesto');
            $this->appendText($dom, $totalImpuesto, 'codigo', $imp['codigo']);
            $this->appendText($dom, $totalImpuesto, 'codigoPorcentaje', $imp['codigoPorcentaje']);
            $this->appendText($dom, $totalImpuesto, 'baseImponible', $this->formatMoney($imp['baseImponible']));
            $this->appendText($dom, $totalImpuesto, 'valor', $this->formatMoney($imp['valor']));
            $totalConImpuestos->appendChild($totalImpuesto);
        }

        $this->appendText($dom, $infoLiquidacion, 'importeTotal', $this->formatMoney($comprobante->total ?? 0));
        $this->appendText($dom, $infoLiquidacion, 'moneda', 'DOLAR');

        $pagos = $dom->createElement('pagos');
        $infoLiquidacion->appendChild($pagos);
        $pago = $dom->createElement('pago');
        $this->appendText($dom, $pago, 'formaPago', '01');
        $this->appendText($dom, $pago, 'total', $this->formatMoney($comprobante->total ?? 0));
        $pagos->appendChild($pago);

        $detalles = $dom->createElement('detalles');
        $liquidacion->appendChild($detalles);

        foreach ($comprobante->detalles ?? [] as $detalle) {
            $detalleNode = $dom->createElement('detalle');
            $codigo = (string) ($detalle->producto_id ?? '');
            if (!empty($codigo)) {
                $this->appendText($dom, $detalleNode, 'codigoPrincipal', $codigo);
            }
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

        // InfoAdicional
        if (!empty($comprobante->email_cliente)) {
            $infoAdicional = $dom->createElement('infoAdicional');
            $campo = $dom->createElement('campoAdicional', $comprobante->email_cliente);
            $campo->setAttribute('nombre', 'Email');
            $infoAdicional->appendChild($campo);
            $liquidacion->appendChild($infoAdicional);
        }

        $xmlString = $dom->saveXML();

        return [
            'xml' => $xmlString,
            'clave_acceso' => $claveAcceso
        ];
    }

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

        $claveAcceso = $comprobante->clave_acceso ?: (new SriClaveAccesoService())->generarFactura(
            $fechaClave,
            $ruc,
            $ambiente,
            $serie,
            $secuencial,
            $codigoNumerico,
            $tipoComprobante,
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

        // Los totales usan las filas resumen; las filas por detalle se emiten dentro de cada item.
        $impuestosTotales = collect($comprobante->impuestos ?? [])
            ->filter(fn ($impuesto) => $impuesto->comprobante_detalle_id === null);

        if ($impuestosTotales->isEmpty()) {
            $impuestosTotales = collect($comprobante->impuestos ?? []);
        }

        // 1. Consolidar impuestos en un array asociativo
        $impuestosConsolidados = [];
        foreach ($impuestosTotales as $impuesto) {
            $codigo = $this->mapCodigoImpuesto($impuesto);
            $porcentaje = $this->mapCodigoPorcentaje($impuesto);
            $key = $codigo . '_' . $porcentaje;

            if (!isset($impuestosConsolidados[$key])) {
                $impuestosConsolidados[$key] = [
                    'codigo' => $codigo,
                    'codigoPorcentaje' => $porcentaje,
                    'tarifa' => (float) ($impuesto->tarifa ?? 0),
                    'baseImponible' => 0.0,
                    'valor' => 0.0
                ];
            }
            $impuestosConsolidados[$key]['baseImponible'] += (float) ($impuesto->base_imponible ?? 0);
            $impuestosConsolidados[$key]['valor'] += (float) ($impuesto->valor ?? 0);
        }

        // 2. Crear los nodos a partir de los datos consolidados
        foreach ($impuestosConsolidados as $imp) {
            $totalImpuesto = $dom->createElement('totalImpuesto');
            $this->appendText($dom, $totalImpuesto, 'codigo', $imp['codigo']);
            $this->appendText($dom, $totalImpuesto, 'codigoPorcentaje', $imp['codigoPorcentaje']);
            $this->appendText($dom, $totalImpuesto, 'baseImponible', $this->formatMoney($imp['baseImponible']));
            $this->appendText($dom, $totalImpuesto, 'tarifa', $this->formatMoney($imp['tarifa']));
            $this->appendText($dom, $totalImpuesto, 'valor', $this->formatMoney($imp['valor']));
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
            $codigo = (string) ($detalle->producto_id ?? '');
                if (!empty($codigo)) {
                    $this->appendText($dom, $detalleNode, 'codigoPrincipal', $codigo);
                }
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

    public function generarXmlGuiaRemision(Comprobante $comprobante): array
    {
        $company                = $comprobante->company;
        $establecimiento        = $comprobante->establecimiento;
        $guiaData               = $comprobante->guia_remision_data ?? [];
        $cliente                = $comprobante->cliente;

        $fechaEmision    = $this->formatFechaEmision($comprobante->fecha_emision);
        $fechaClave      = $this->formatFechaClave($comprobante->fecha_emision);
        $tipoComprobante = '06'; // Guía de Remisión
        $ruc             = (string) ($company->ruc ?? '');
        $ambiente        = $this->mapAmbiente($comprobante->ambiente ?? $company->ambiente ?? 'PRODUCCION');
        $serie           = $this->buildSerie($comprobante);
        $secuencial      = $this->padLeft((string) $comprobante->secuencial, 9);
        $codigoNumerico  = $this->padLeft((string) ($comprobante->secuencial ?? random_int(1, 99999999)), 8);
        $tipoEmision     = '1';

        $claveAcceso = $comprobante->clave_acceso ?: (new SriClaveAccesoService())->generarFactura(
            $fechaClave, $ruc, $ambiente, $serie, $secuencial, $codigoNumerico, $tipoComprobante, $tipoEmision
        );

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $root = $dom->createElement('guiaRemision');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', '1.1.0');
        $dom->appendChild($root);

        // ── infoTributaria ──────────────────────────────────────────
        $infoTributaria = $dom->createElement('infoTributaria');
        $root->appendChild($infoTributaria);
        $this->appendText($dom, $infoTributaria, 'ambiente',        $ambiente);
        $this->appendText($dom, $infoTributaria, 'tipoEmision',     $tipoEmision);
        $this->appendText($dom, $infoTributaria, 'razonSocial',     $company->razon_social ?? '');
        $this->appendText($dom, $infoTributaria, 'nombreComercial', $company->nombre_comercial ?? $company->razon_social ?? '');
        $this->appendText($dom, $infoTributaria, 'ruc',             $ruc);
        $this->appendText($dom, $infoTributaria, 'claveAcceso',     $claveAcceso);
        $this->appendText($dom, $infoTributaria, 'codDoc',          $tipoComprobante);
        $this->appendText($dom, $infoTributaria, 'estab',           $comprobante->codigo_establecimiento ?? substr($serie, 0, 3));
        $this->appendText($dom, $infoTributaria, 'ptoEmi',          $comprobante->punto_emision_codigo  ?? substr($serie, 3, 3));
        $this->appendText($dom, $infoTributaria, 'secuencial',      $secuencial);
        $this->appendText($dom, $infoTributaria, 'dirMatriz',       $company->direccion_matriz ?? '');

        if (($company->agente_retencion ?? 'NO') === 'SI') {
            $this->appendText($dom, $infoTributaria, 'agenteRetencion', $company->numero_resolucion_agente_retencion ?? '');
        }
        $rimpe = $this->buildRimpe($company->regimen_tributario ?? '');
        if ($rimpe) {
            $this->appendText($dom, $infoTributaria, 'contribuyenteRimpe', $rimpe);
        }

        // ── infoGuiaRemision ────────────────────────────────────────
        $infoGR = $dom->createElement('infoGuiaRemision');
        $root->appendChild($infoGR);

        $this->appendText($dom, $infoGR, 'dirEstablecimiento', $establecimiento->direccion ?? $company->direccion_matriz ?? '');
        $this->appendText($dom, $infoGR, 'dirPartida', $guiaData['direccion_partida'] ?? $establecimiento->direccion ?? '');
        $this->appendText($dom, $infoGR, 'razonSocialTransportista', $guiaData['transportista_nombre'] ?? 'TRANSPORTISTA');
        // Identificacion transportista: RUC o CEDULA. Si es 10 digitos asume CEDULA (05), si es 13 asume RUC (04)
        $idTransportista = $guiaData['transportista_identificacion'] ?? '9999999999999';
        $tipoIdTransp = strlen($idTransportista) === 10 ? '05' : (strlen($idTransportista) === 13 ? '04' : '06');
        $this->appendText($dom, $infoGR, 'tipoIdentificacionTransportista', $tipoIdTransp);
        $this->appendText($dom, $infoGR, 'rucTransportista', $idTransportista);
        $this->appendText($dom, $infoGR, 'obligadoContabilidad', $company->obligado_contabilidad ?? 'NO');
        $this->appendText($dom, $infoGR, 'fechaIniTransporte', $this->formatFechaEmision($guiaData['fecha_inicio_transporte'] ?? $comprobante->fecha_emision));
        $this->appendText($dom, $infoGR, 'fechaFinTransporte', $this->formatFechaEmision($guiaData['fecha_fin_transporte'] ?? $comprobante->fecha_emision));
        $this->appendText($dom, $infoGR, 'placa', $guiaData['placa_vehiculo'] ?? 'XXX0000');

        // ── destinatarios ───────────────────────────────────────────
        $destinatariosNode = $dom->createElement('destinatarios');
        $root->appendChild($destinatariosNode);

        $destinatarioNode = $dom->createElement('destinatario');
        $destinatariosNode->appendChild($destinatarioNode);

        $this->appendText($dom, $destinatarioNode, 'identificacionDestinatario', $cliente->identificacion ?? '9999999999999');
        $this->appendText($dom, $destinatarioNode, 'razonSocialDestinatario', $cliente->razon_social ?? 'CONSUMIDOR FINAL');
        $this->appendText($dom, $destinatarioNode, 'dirDestinatario', $guiaData['direccion_destino'] ?? $cliente->direccion ?? 'SD');
        $this->appendText($dom, $destinatarioNode, 'motivoTraslado', $guiaData['motivo_traslado'] ?? 'VENTA');
        
        // Destino doc aduanero es opcional, lo omitimos
        $this->appendText($dom, $destinatarioNode, 'codEstabDestino', '001'); // Siempre obligatorio, típicamente 001 o depende de sucursal
        if (!empty($guiaData['ruta'])) {
            $this->appendText($dom, $destinatarioNode, 'ruta', $guiaData['ruta']);
        }

        // Si la guia está sustentada en una factura, llenar codDocSustento (01)
        if ($comprobante->comprobanteModificado) {
            $this->appendText($dom, $destinatarioNode, 'codDocSustento', '01');
            $this->appendText($dom, $destinatarioNode, 'numDocSustento', $comprobante->comprobanteModificado->secuencial_formateado);
            if (!empty($comprobante->comprobanteModificado->numero_autorizacion)) {
                $this->appendText($dom, $destinatarioNode, 'numAutDocSustento', $comprobante->comprobanteModificado->numero_autorizacion);
            }
            $this->appendText($dom, $destinatarioNode, 'fechaEmisionDocSustento', $this->formatFechaEmision($comprobante->comprobanteModificado->fecha_emision));
        }

        $detallesNode = $dom->createElement('detalles');
        $destinatarioNode->appendChild($detallesNode);

        foreach ($comprobante->detalles ?? [] as $detalle) {
            $detalleNode = $dom->createElement('detalle');
            $this->appendText($dom, $detalleNode, 'codigoInterno', $detalle->producto_id ?? '001');
            $this->appendText($dom, $detalleNode, 'descripcion', $detalle->descripcion);
            $this->appendText($dom, $detalleNode, 'cantidad', number_format($detalle->cantidad, 2, '.', ''));
            $detallesNode->appendChild($detalleNode);
        }

        // infoAdicional
        if (!empty($cliente->email ?? null)) {
            $infoAdicional = $dom->createElement('infoAdicional');
            $root->appendChild($infoAdicional);
            $campo = $dom->createElement('campoAdicional', $cliente->email);
            $campo->setAttribute('nombre', 'Email');
            $infoAdicional->appendChild($campo);
        }

        return [
            'xml'          => $dom->saveXML(),
            'clave_acceso' => $claveAcceso,
        ];
    }

    public function generarXmlNotaDebito(Comprobante $comprobante): array
    {
        $company                = $comprobante->company;
        $cliente                = $comprobante->cliente;
        $establecimiento        = $comprobante->establecimiento;
        $comprobanteModificado  = $comprobante->comprobanteModificado;

        $fechaEmision    = $this->formatFechaEmision($comprobante->fecha_emision);
        $fechaClave      = $this->formatFechaClave($comprobante->fecha_emision);
        $tipoComprobante = '05'; // Nota de Débito
        $ruc             = (string) ($company->ruc ?? '');
        $ambiente        = $this->mapAmbiente($comprobante->ambiente ?? $company->ambiente ?? 'PRODUCCION');
        $serie           = $this->buildSerie($comprobante);
        $secuencial      = $this->padLeft((string) $comprobante->secuencial, 9);
        $codigoNumerico  = $this->padLeft((string) ($comprobante->secuencial ?? random_int(1, 99999999)), 8);
        $tipoEmision     = '1';

        $claveAcceso = $comprobante->clave_acceso ?: (new SriClaveAccesoService())->generarFactura(
            $fechaClave, $ruc, $ambiente, $serie, $secuencial, $codigoNumerico, $tipoComprobante, $tipoEmision
        );

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $root = $dom->createElement('notaDebito');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', '1.0.0');
        $dom->appendChild($root);

        // ── infoTributaria ──────────────────────────────────────────
        $infoTributaria = $dom->createElement('infoTributaria');
        $root->appendChild($infoTributaria);
        $this->appendText($dom, $infoTributaria, 'ambiente',        $ambiente);
        $this->appendText($dom, $infoTributaria, 'tipoEmision',     $tipoEmision);
        $this->appendText($dom, $infoTributaria, 'razonSocial',     $company->razon_social ?? '');
        $this->appendText($dom, $infoTributaria, 'nombreComercial', $company->nombre_comercial ?? $company->razon_social ?? '');
        $this->appendText($dom, $infoTributaria, 'ruc',             $ruc);
        $this->appendText($dom, $infoTributaria, 'claveAcceso',     $claveAcceso);
        $this->appendText($dom, $infoTributaria, 'codDoc',          $tipoComprobante);
        $this->appendText($dom, $infoTributaria, 'estab',           $comprobante->codigo_establecimiento ?? substr($serie, 0, 3));
        $this->appendText($dom, $infoTributaria, 'ptoEmi',          $comprobante->punto_emision_codigo  ?? substr($serie, 3, 3));
        $this->appendText($dom, $infoTributaria, 'secuencial',      $secuencial);
        $this->appendText($dom, $infoTributaria, 'dirMatriz',       $company->direccion_matriz ?? '');

        if (($company->agente_retencion ?? 'NO') === 'SI') {
            $this->appendText($dom, $infoTributaria, 'agenteRetencion', $company->numero_resolucion_agente_retencion ?? '');
        }
        $rimpe = $this->buildRimpe($company->regimen_tributario ?? '');
        if ($rimpe) {
            $this->appendText($dom, $infoTributaria, 'contribuyenteRimpe', $rimpe);
        }

        // ── infoNotaDebito ──────────────────────────────────────────
        $infoND = $dom->createElement('infoNotaDebito');
        $root->appendChild($infoND);
        $this->appendText($dom, $infoND, 'fechaEmision',              $fechaEmision);
        $this->appendText($dom, $infoND, 'dirEstablecimiento',        $establecimiento->direccion ?? $company->direccion_matriz ?? '');
        $this->appendText($dom, $infoND, 'tipoIdentificacionComprador', $this->mapTipoIdentificacion($cliente));
        $this->appendText($dom, $infoND, 'razonSocialComprador',      $cliente->razon_social  ?? 'CONSUMIDOR FINAL');
        $this->appendText($dom, $infoND, 'identificacionComprador',   $cliente->identificacion ?? '9999999999999');
        $this->appendText($dom, $infoND, 'obligadoContabilidad',      $company->obligado_contabilidad ?? 'NO');
        $this->appendText($dom, $infoND, 'codDocModificado',          '01'); // Factura
        $this->appendText($dom, $infoND, 'numDocModificado',
            $comprobanteModificado ? $comprobanteModificado->secuencial_formateado : '000-000-000000000'
        );
        $this->appendText($dom, $infoND, 'fechaEmisionDocSustento',
            $comprobanteModificado ? $this->formatFechaEmision($comprobanteModificado->fecha_emision) : $fechaEmision
        );
        $this->appendText($dom, $infoND, 'totalSinImpuestos', $this->formatMoney($comprobante->subtotal_sin_impuestos ?? 0));

        // totalConImpuestos
        $totalConImpuestos = $dom->createElement('totalConImpuestos');
        $infoND->appendChild($totalConImpuestos);

        $impuestosTotales = collect($comprobante->impuestos ?? [])
            ->filter(fn ($i) => $i->comprobante_detalle_id === null);
        if ($impuestosTotales->isEmpty()) {
            $impuestosTotales = collect($comprobante->impuestos ?? []);
        }

        $impuestosConsolidados = [];
        foreach ($impuestosTotales as $impuesto) {
            $cod  = $this->mapCodigoImpuesto($impuesto);
            $pct  = $this->mapCodigoPorcentaje($impuesto);
            $key  = $cod . '_' . $pct;
            if (!isset($impuestosConsolidados[$key])) {
                $impuestosConsolidados[$key] = ['codigo' => $cod, 'codigoPorcentaje' => $pct, 'baseImponible' => 0.0, 'valor' => 0.0];
            }
            $impuestosConsolidados[$key]['baseImponible'] += (float) ($impuesto->base_imponible ?? 0);
            $impuestosConsolidados[$key]['valor']         += (float) ($impuesto->valor ?? 0);
        }
        foreach ($impuestosConsolidados as $imp) {
            $ti = $dom->createElement('totalImpuesto');
            $this->appendText($dom, $ti, 'codigo',           $imp['codigo']);
            $this->appendText($dom, $ti, 'codigoPorcentaje', $imp['codigoPorcentaje']);
            $this->appendText($dom, $ti, 'baseImponible',    $this->formatMoney($imp['baseImponible']));
            $this->appendText($dom, $ti, 'valor',            $this->formatMoney($imp['valor']));
            $totalConImpuestos->appendChild($ti);
        }

        $this->appendText($dom, $infoND, 'valorTotal', $this->formatMoney($comprobante->total ?? 0));

        // ── motivos ─────────────────────────────────────────────────
        // Los detalles de la Nota de Débito se mapean como <motivos><motivo>
        $motivosNode = $dom->createElement('motivos');
        $root->appendChild($motivosNode);

        foreach ($comprobante->detalles ?? [] as $detalle) {
            $motivoNode = $dom->createElement('motivo');
            $subtotalDetalle = (float) ($detalle->subtotal ?? ($detalle->cantidad * $detalle->precio_unitario));
            $this->appendText($dom, $motivoNode, 'razon', $detalle->descripcion ?? 'Cargo adicional');
            $this->appendText($dom, $motivoNode, 'valor', $this->formatMoney($subtotalDetalle));
            $motivosNode->appendChild($motivoNode);
        }

        // Si no hay detalles, agregar un motivo por defecto desde el campo motivo_modificacion
        if (empty($comprobante->detalles?->toArray())) {
            $motivoNode = $dom->createElement('motivo');
            $this->appendText($dom, $motivoNode, 'razon', $comprobante->motivo_modificacion ?? 'Cargo adicional');
            $this->appendText($dom, $motivoNode, 'valor', $this->formatMoney($comprobante->total ?? 0));
            $motivosNode->appendChild($motivoNode);
        }

        // infoAdicional
        if (!empty($cliente->email ?? null)) {
            $infoAdicional = $dom->createElement('infoAdicional');
            $root->appendChild($infoAdicional);
            $campo = $dom->createElement('campoAdicional', $cliente->email);
            $campo->setAttribute('nombre', 'Email');
            $infoAdicional->appendChild($campo);
        }

        return [
            'xml'          => $dom->saveXML(),
            'clave_acceso' => $claveAcceso,
        ];
    }

    public function generarXmlNotaCredito(Comprobante $comprobante): array
    {
        $company = $comprobante->company;
        $cliente = $comprobante->cliente;
        $establecimiento = $comprobante->establecimiento;
        $comprobanteModificado = $comprobante->comprobanteModificado;

        $fechaEmision = $this->formatFechaEmision($comprobante->fecha_emision);
        $fechaClave = $this->formatFechaClave($comprobante->fecha_emision);
        $tipoComprobante = '04'; // Nota de Crédito
        $ruc = (string) ($company->ruc ?? '');
        $ambiente = $this->mapAmbiente($comprobante->ambiente ?? $company->ambiente ?? 'PRODUCCION');
        $serie = $this->buildSerie($comprobante);
        $secuencial = $this->padLeft((string) $comprobante->secuencial, 9);
        $codigoNumerico = $this->padLeft((string) ($comprobante->secuencial ?? random_int(1, 99999999)), 8);
        $tipoEmision = '1';

        $claveAcceso = $comprobante->clave_acceso ?: (new SriClaveAccesoService())->generarFactura(
            $fechaClave,
            $ruc,
            $ambiente,
            $serie,
            $secuencial,
            $codigoNumerico,
            $tipoComprobante,
            $tipoEmision
        );

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $notaCredito = $dom->createElement('notaCredito');
        $notaCredito->setAttribute('id', 'comprobante');
        $notaCredito->setAttribute('version', '1.1.0');
        $dom->appendChild($notaCredito);

        $infoTributaria = $dom->createElement('infoTributaria');
        $notaCredito->appendChild($infoTributaria);

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

        $infoNotaCredito = $dom->createElement('infoNotaCredito');
        $notaCredito->appendChild($infoNotaCredito);

        $this->appendText($dom, $infoNotaCredito, 'fechaEmision', $fechaEmision);
        $this->appendText($dom, $infoNotaCredito, 'dirEstablecimiento', $establecimiento->direccion ?? $company->direccion_matriz ?? '');
        $this->appendText($dom, $infoNotaCredito, 'tipoIdentificacionComprador', $this->mapTipoIdentificacion($cliente));
        $this->appendText($dom, $infoNotaCredito, 'razonSocialComprador', $cliente->razon_social ?? 'CONSUMIDOR FINAL');
        $this->appendText($dom, $infoNotaCredito, 'identificacionComprador', $cliente->identificacion ?? '9999999999999');
        $this->appendText($dom, $infoNotaCredito, 'obligadoContabilidad', $company->obligado_contabilidad ?? 'NO');
        
        $this->appendText($dom, $infoNotaCredito, 'codDocModificado', $comprobanteModificado ? '01' : '01');
        $this->appendText($dom, $infoNotaCredito, 'numDocModificado', $comprobanteModificado ? $comprobanteModificado->secuencial_formateado : '000-000-000000000');
        $this->appendText($dom, $infoNotaCredito, 'fechaEmisionDocSustento', $comprobanteModificado ? $this->formatFechaEmision($comprobanteModificado->fecha_emision) : $fechaEmision);
        $this->appendText($dom, $infoNotaCredito, 'totalSinImpuestos', $this->formatMoney($comprobante->subtotal_sin_impuestos ?? 0));
        $this->appendText($dom, $infoNotaCredito, 'valorModificacion', $this->formatMoney($comprobante->total ?? 0));
        $this->appendText($dom, $infoNotaCredito, 'moneda', 'DOLAR');

        $totalConImpuestos = $dom->createElement('totalConImpuestos');
        $infoNotaCredito->appendChild($totalConImpuestos);

        $impuestosTotales = collect($comprobante->impuestos ?? [])
            ->filter(fn ($impuesto) => $impuesto->comprobante_detalle_id === null);

        if ($impuestosTotales->isEmpty()) {
            $impuestosTotales = collect($comprobante->impuestos ?? []);
        }

        $impuestosConsolidados = [];
        foreach ($impuestosTotales as $impuesto) {
            $codigo = $this->mapCodigoImpuesto($impuesto);
            $porcentaje = $this->mapCodigoPorcentaje($impuesto);
            $key = $codigo . '_' . $porcentaje;

            if (!isset($impuestosConsolidados[$key])) {
                $impuestosConsolidados[$key] = [
                    'codigo' => $codigo,
                    'codigoPorcentaje' => $porcentaje,
                    'baseImponible' => 0.0,
                    'valor' => 0.0,
                ];
            }

            $impuestosConsolidados[$key]['baseImponible'] += (float) ($impuesto->base_imponible ?? 0);
            $impuestosConsolidados[$key]['valor'] += (float) ($impuesto->valor ?? 0);
        }

        foreach ($impuestosConsolidados as $impCons) {
            $totalImpuesto = $dom->createElement('totalImpuesto');
            $this->appendText($dom, $totalImpuesto, 'codigo', $impCons['codigo']);
            $this->appendText($dom, $totalImpuesto, 'codigoPorcentaje', $impCons['codigoPorcentaje']);
            $this->appendText($dom, $totalImpuesto, 'baseImponible', $this->formatMoney($impCons['baseImponible']));
            $this->appendText($dom, $totalImpuesto, 'valor', $this->formatMoney($impCons['valor']));
            $totalConImpuestos->appendChild($totalImpuesto);
        }
        
        $this->appendText($dom, $infoNotaCredito, 'motivo', $comprobante->motivo_modificacion ?? 'Devolucion');

        $detalles = $dom->createElement('detalles');
        $notaCredito->appendChild($detalles);

        foreach ($comprobante->detalles ?? [] as $detalle) {
            $detalleNode = $dom->createElement('detalle');
            $codigo = $detalle->producto ? $detalle->producto->codigo : null;
            if ($codigo) {
                $this->appendText($dom, $detalleNode, 'codigoInterno', $codigo);
            }
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
            $notaCredito->appendChild($infoAdicional);
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
        if ($tipo === 'CONSUMIDOR_FINAL' || $tipo === '07') {
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
