<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; color: #333; line-height: 1.5; background-color: #f4f4f4; padding: 20px; }
        .container { background-color: #fff; border-radius: 8px; padding: 20px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background-color: #004aad; color: #fff; padding: 15px; border-radius: 8px 8px 0 0; margin: -20px -20px 20px -20px; display: flex; align-items: center; justify-content: space-between;}
        .header h2 { margin: 0; font-size: 18px; }
        .header p { margin: 0; font-size: 12px; }
        .content { margin-bottom: 20px; }
        .info-box { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #004aad; }
        .footer { font-size: 12px; color: #777; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h2>Factura Electrónica</h2>
                <p>{{ $comprobante->company->razon_social }}</p>
            </div>
        </div>

        <div class="content">
            <p>Estimado(a) <strong>{{ $comprobante->cliente->razon_social }}</strong>,</p>
            <p>Adjuntamos su factura electrónica correspondiente a su compra.</p>

            <div class="info-box">
                <p style="margin: 0;"><strong>Nº Factura:</strong> {{ $comprobante->secuencial_formateado }}</p>
                <p style="margin: 5px 0 0 0;"><strong>Fecha de emisión:</strong> {{ \Carbon\Carbon::parse($comprobante->fecha_emision)->format('d/m/Y') }}</p>
                <p style="margin: 5px 0 0 0;"><strong>Emisor:</strong> {{ $comprobante->company->razon_social }}</p>
            </div>

            <p>Se adjuntan los siguientes documentos:</p>
            <ul>
                <li>Factura en formato PDF</li>
                <li>Archivo XML autorizado por el SRI</li>
            </ul>

            <p>Si tiene alguna duda, puede contactarnos directamente respondiendo este correo.</p>
        </div>

        <div class="footer">
            Este es un mensaje generado automáticamente. Por favor no responda a este correo si no es necesario.
        </div>
    </div>
</body>
</html>
