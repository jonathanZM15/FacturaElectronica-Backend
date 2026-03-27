<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 30px;
        }
        .header {
            border-bottom: 3px solid #ff9800;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #ff9800;
            margin: 0;
            font-size: 24px;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ff9800;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .warning-box strong {
            color: #ff6b00;
        }
        .info-section {
            margin: 25px 0;
            background: #fff;
            padding: 15px;
            border-radius: 4px;
        }
        .info-section h3 {
            color: #ff9800;
            margin-top: 0;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: #ff9800;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 0;
            font-weight: bold;
        }
        .button:hover {
            background: #ff6b00;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            font-size: 12px;
            color: #999;
            text-align: center;
        }
        .highlight {
            color: #d32f2f;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ Advertencia: Tu cuenta será eliminada</h1>
        </div>

        <p>Estimado cliente de <strong>{{ $company->razon_social }}</strong>,</p>

        <div class="warning-box">
            <strong>ATENCIÓN IMPORTANTE:</strong><br>
            Tu cuenta de emisor será <span class="highlight">eliminada permanentemente en {{ $deletionDate }}</span> 
            debido a que ha permanecido inactiva durante más de un año.
        </div>

        <div class="info-section">
            <h3>📌 Información de tu Empresa</h3>
            <ul>
                <li><strong>RUC:</strong> {{ $company->ruc }}</li>
                <li><strong>Razón Social:</strong> {{ $company->razon_social }}</li>
                <li><strong>Nombre Comercial:</strong> {{ $company->nombre_comercial }}</li>
            </ul>
        </div>

        <div class="info-section">
            <h3>📥 Descarga tu Respaldo</h3>
            <p>Hemos generado automáticamente un archivo Excel que contiene <strong>TODOS tus datos</strong>:</p>
            <ul>
                <li>✓ Información general de tu empresa</li>
                <li>✓ Todas tus facturas emitidas</li>
                <li>✓ Productos registrados</li>
                <li>✓ Clientes registrados</li>
                <li>✓ Planes de facturación activos</li>
                <li>✓ Usuarios vinculados</li>
            </ul>
            <p style="text-align: center;">
                <a href="{{ $backupDownloadUrl }}" class="button">📥 Descargar mi Respaldo</a>
            </p>
        </div>

        <div class="info-section">
            <h3>🔄 ¿Qué puedo hacer?</h3>
            <p>Tienes disponibles las siguientes opciones:</p>
            <ol>
                <li><strong>Descargar el respaldo:</strong> Guarda tu información antes de que sea eliminada</li>
                <li><strong>Reactivar tu cuenta:</strong> Si regresas a usar nuestro servicio, puedes reactivarla en cualquier momento</li>
                <li><strong>Importar datos posteriormente:</strong> Si lo necesitas después, puedes importar este archivo para restaurar tu cuenta</li>
            </ol>
        </div>

        <div class="warning-box" style="background: #ffe0e0; border-left-color: #d32f2f;">
            <strong style="color: #d32f2f;">⏰ TIEMPO RESTANTE:</strong> 
            Tienes <span class="highlight">3 días</span> antes de la eliminación permanente de tu cuenta.
        </div>

        <div class="info-section">
            <h3>📧 Próximas Notificaciones</h3>
            <p>Recibirás un último correo electrónico notificándote sobre:</p>
            <ul>
                <li>Confirmación final de eliminación</li>
                <li>Instrucciones para reactivar tu cuenta si cambias de opinión</li>
            </ul>
        </div>

        <div class="footer">
            <p>Este es un mensaje automático del sistema de facturación electronica.</p>
            <p>Si tienes preguntas o necesitas ayuda, contacta a nuestro equipo de soporte.</p>
            <p>© {{ now()->year }} Firma Electrónica. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
