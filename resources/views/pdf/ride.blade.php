<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura Electrónica {{ $comprobante->secuencial_formateado }}</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; margin: 0; padding: 0; }
        .border { border: 1px solid #000; padding: 8px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 4px; vertical-align: top; }
        .table-items th, .table-items td { border: 1px solid #000; }
        .table-items th { background-color: #f4f4f4; text-align: center; font-size: 9px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .mt-2 { margin-top: 10px; }
        .mb-2 { margin-bottom: 10px; }
        .fw-bold { font-weight: bold; }
        .fs-large { font-size: 13px; }
        .fs-xlarge { font-size: 16px; }
        .w-50 { width: 50%; }
        .w-100 { width: 100%; }
        .barcode { text-align: center; margin-top: 10px; }
        .barcode img { max-width: 100%; height: 50px; }
        .no-margin { margin: 0; }
    </style>
</head>
<body>
    <table class="w-100 mb-2" style="margin-bottom: 0px;">
        <tr>
            <td class="w-50" style="padding-right: 10px;">
                <div class="text-center mb-2" style="height: 100px; display: table-cell; vertical-align: middle; width: 300px;">
                    @if($logoBase64)
                        <img src="{{ $logoBase64 }}" style="max-height: 90px; max-width: 100%;">
                    @endif
                </div>
                <div class="border">
                    <div class="fw-bold fs-large mb-2">{{ $company->razon_social }}</div>
                    @if($company->nombre_comercial && $company->nombre_comercial !== $company->razon_social)
                        <div class="mb-2">{{ $company->nombre_comercial }}</div>
                    @endif
                    <div><strong>Dirección Matriz:</strong> {{ $company->direccion_matriz }}</div>
                    <div><strong>Dirección Establecimiento:</strong> {{ $establecimiento->direccion ?: $company->direccion_matriz }}</div>
                    
                    @if($company->contribuyente_especial)
                        <div><strong>Contribuyente Especial Nro:</strong> {{ $company->numero_resolucion_contribuyente_especial }}</div>
                    @endif
                    
                    <div><strong>OBLIGADO A LLEVAR CONTABILIDAD:</strong> {{ $company->obligado_contabilidad ? 'SI' : 'NO' }}</div>
                    
                    @if($company->regimen_tributario)
                        <div><strong>CONTRIBUYENTE RÉGIMEN RIMPE</strong></div>
                    @endif
                    @if($company->agente_retencion)
                        <div><strong>Agente de Retención Resolución No.</strong> {{ $company->numero_resolucion_agente_retencion }}</div>
                    @endif
                </div>
            </td>
            <td class="w-50 border">
                <div class="fw-bold fs-large">R.U.C.: {{ $company->ruc }}</div>
                <div class="fw-bold fs-xlarge text-center mt-2 mb-2" style="letter-spacing: 2px;">FACTURA</div>
                <div class="mb-2">No. {{ $comprobante->secuencial_formateado }}</div>
                
                <div class="fw-bold">NÚMERO DE AUTORIZACIÓN</div>
                <div class="mb-2" style="font-size: 11px;">{{ $comprobante->numero_autorizacion ?: $comprobante->clave_acceso }}</div>
                
                <table class="w-100 mb-2">
                    <tr>
                        <td style="padding: 0;"><strong>FECHA Y HORA DE AUTORIZACIÓN:</strong></td>
                        <td style="padding: 0;">{{ $comprobante->fecha_autorizacion ? \Carbon\Carbon::parse($comprobante->fecha_autorizacion)->format('Y-m-d H:i:s') : 'EN PROCESO' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 0;"><strong>AMBIENTE:</strong></td>
                        <td style="padding: 0;">{{ $comprobante->ambiente === 'PRODUCCION' ? 'PRODUCCION' : 'PRUEBAS' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 0;"><strong>EMISIÓN:</strong></td>
                        <td style="padding: 0;">NORMAL</td>
                    </tr>
                </table>
                
                <div class="fw-bold text-center">CLAVE DE ACCESO</div>
                <div class="barcode">
                    <img src="{{ $barcodeBase64 }}" alt="Barcode">
                </div>
                <div class="text-center" style="font-size: 10px; margin-top: 5px;">{{ $comprobante->clave_acceso }}</div>
            </td>
        </tr>
    </table>

    <div class="border mb-2 mt-2">
        <table class="w-100">
            <tr>
                <td style="width: 60%;"><strong>Razón Social / Nombres y Apellidos:</strong> {{ $cliente->razon_social }}</td>
                <td style="width: 40%;"><strong>Identificación:</strong> {{ $cliente->identificacion }}</td>
            </tr>
            <tr>
                <td><strong>Fecha Emisión:</strong> {{ \Carbon\Carbon::parse($comprobante->fecha_emision)->format('d/m/Y') }}</td>
                <td><strong>Guía Remisión:</strong> </td>
            </tr>
            <tr>
                <td colspan="2"><strong>Dirección Cliente:</strong> {{ $cliente->direccion }}</td>
            </tr>
        </table>
    </div>

    <table class="w-100 table-items mb-2 mt-2">
        <thead>
            <tr>
                <th style="width: 10%;">Cod. Principal</th>
                <th style="width: 10%;">Cod. Auxiliar</th>
                <th style="width: 40%;">Descripción</th>
                <th style="width: 8%;">Cant</th>
                <th style="width: 10%;">Precio Unitario</th>
                <th style="width: 10%;">Descuento</th>
                <th style="width: 12%;">Total Sin Impuestos</th>
            </tr>
        </thead>
        <tbody>
            @foreach($comprobante->detalles as $detalle)
            <tr>
                <td>{{ $detalle->producto ? $detalle->producto->codigo : 'pprueba' }}</td>
                <td>{{ $detalle->producto ? $detalle->producto->codigo_auxiliar : '' }}</td>
                <td>{{ $detalle->descripcion }}</td>
                <td class="text-right">{{ number_format($detalle->cantidad, 2) }}</td>
                <td class="text-right">{{ number_format($detalle->precio_unitario, 6) }}</td>
                <td class="text-right">{{ number_format($detalle->descuento, 2) }}</td>
                <td class="text-right">{{ number_format($detalle->subtotal, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="w-100 mt-2">
        <tr>
            <td style="width: 65%; padding-right: 10px; padding-left: 0; padding-top: 0;">
                <div class="border mb-2">
                    <div class="fw-bold mb-2">Información Adicional</div>
                    <table class="w-100">
                        @if($cliente->direccion)
                        <tr>
                            <td style="width: 25%; padding: 2px;">Direccion</td>
                            <td style="padding: 2px;">{{ $cliente->direccion }}</td>
                        </tr>
                        @endif
                        @if($cliente->telefono)
                        <tr>
                            <td style="padding: 2px;">Telefono</td>
                            <td style="padding: 2px;">{{ $cliente->telefono }}</td>
                        </tr>
                        @endif
                        @if($cliente->email)
                        <tr>
                            <td style="padding: 2px;">Email</td>
                            <td style="padding: 2px;">{{ $cliente->email }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
                
                <div class="border mt-2">
                    <table class="w-100">
                        <thead>
                            <tr>
                                <th style="text-align: left; padding: 2px;">Forma Pago</th>
                                <th class="text-right" style="padding: 2px;">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 2px;">SIN UTILIZACION DEL SISTEMA FINANCIERO</td>
                                <td class="text-right" style="padding: 2px;">{{ number_format($comprobante->total, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </td>
            <td style="width: 35%; padding: 0;">
                <table class="w-100 table-items">
                    <tr>
                        <td style="padding: 3px;">SUBTOTAL 15%</td>
                        <td class="text-right" style="padding: 3px;">{{ number_format($comprobante->subtotal_iva, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 3px;">SUBTOTAL 0%</td>
                        <td class="text-right" style="padding: 3px;">{{ number_format($comprobante->subtotal_iva_0, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 3px;">SUBTOTAL no objeto de IVA</td>
                        <td class="text-right" style="padding: 3px;">{{ number_format($comprobante->subtotal_no_objeto, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 3px;">SUBTOTAL exento de IVA</td>
                        <td class="text-right" style="padding: 3px;">{{ number_format($comprobante->subtotal_exento, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 3px;">SUBTOTAL SIN IMPUESTOS</td>
                        <td class="text-right" style="padding: 3px;">{{ number_format($comprobante->subtotal_sin_impuestos, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 3px;">TOTAL Descuento</td>
                        <td class="text-right" style="padding: 3px;">{{ number_format($comprobante->total_descuento, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 3px;">IVA 15%</td>
                        <td class="text-right" style="padding: 3px;">{{ number_format($comprobante->total_iva, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 3px;"><strong>IMPORTE TOTAL</strong></td>
                        <td class="text-right" style="padding: 3px;"><strong>{{ number_format($comprobante->total, 2) }}</strong></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
