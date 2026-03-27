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
            border-bottom: 3px solid #d32f2f;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #d32f2f;
            margin: 0;
            font-size: 28px;
        }
        .critical-box {
            background: #ffebee;
            border-left: 4px solid #d32f2f;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .critical-box strong {
            color: #d32f2f;
            font-size: 18px;
        }
        .info-section {
            margin: 25px 0;
            background: #fff;
            padding: 15px;
            border-radius: 4px;
        }
        .info-section h3 {
            color: #d32f2f;
            margin-top: 0;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: #d32f2f;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 5px;
            font-weight: bold;
        }
        .button:hover {
            background: #b71c1c;
        }
        .button-secondary {
            background: #4caf50;
            padding: 12px 24px;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 5px;
            font-weight: bold;
            display: inline-block;
        }
        .button-secondary:hover {
            background: #388e3c;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            font-size: 12px;
            color: #999;
            text-align: center;
        }
        .countdown {
            font-size: 48px;
            color: #d32f2f;
            text-align: center;
            font-weight: bold;
            margin: 20px 0;
        }
        .countdown-text {
            text-align: center;
            color: #d32f2f;
            font-size: 16px;
            margin: 10px 0;
        }
        .highlight {
            color: #d32f2f;
            font-weight: bold;
        }
        .action-buttons {
            text-align: center;
            margin: 30px 0;
        }
        .step {
            background: #f5f5f5;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
            border-left: 4px solid #d32f2f;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔴 NOTIFICACIÓN FINAL: Tu Cuenta Será Eliminada</h1>
        </div>

        <p style="font-size: 18px;"><strong>Estimado cliente de {{ $company->razon_social }},</strong></p>

        <div class="critical-box">
            <strong>⏰ ACCIÓN REQUERIDA - CUENTA EN ELIMINACIÓN</strong><br><br>
            Tu cuenta será <span class="highlight">ELIMINADA PERMANENTEMENTE</span> en los próximos <strong>{{ $daysRemaining }} días</strong>.
        </div>

        <div class="countdown">
            {{ $daysRemaining }} DÍAS
        </div>
        <div class="countdown-text">
            Tienes {{ $hoursUntilDeletion }} horas para actuar
        </div>

        <div class="info-section">
            <h3>📋 Información de tu Empresa</h3>
            <ul>
                <li><strong>RUC:</strong> {{ $company->ruc }}</li>
                <li><strong>Razón Social:</strong> {{ $company->razon_social }}</li>
            </ul>
        </div>

        <div class="critical-box" style="background: #fff3e0; border-left-color: #ff9800;">
            <strong style="color: #ff9800;">⚠️ IMPORTANTE:</strong><br>
            Esta es tu última notificación. Después de {{ $daysRemaining }} días, tu cuenta y todos tus datos 
            serán <span class="highlight">eliminados permanentemente</span> del sistema.
        </div>

        <div class="info-section">
            <h3>✅ Opciones Disponibles</h3>
            
            <div class="step">
                <strong>OPCIÓN 1: Reactivar tu Cuenta</strong><br>
                <p>Si deseas mantener tu cuenta activa, haz clic en el siguiente botón:</p>
                <div class="action-buttons">
                    <a href="{{ $reactivationUrl }}" class="button-secondary">🔄 Reactivar mi Cuenta</a>
                </div>
            </div>

            <div class="step">
                <strong>OPCIÓN 2: Descargar tu Respaldo</strong><br>
                <p>Descarga un archivo Excel con TODOS tus datos antes de que sean eliminados:</p>
                <ul>
                    <li>✓ Información de la empresa</li>
                    <li>✓ Todas las facturas emitidas</li>
                    <li>✓ Catálogo de productos</li>
                    <li>✓ Base de datos de clientes</li>
                    <li>✓ Planes y suscripciones</li>
                    <li>✓ Usuarios del sistema</li>
                </ul>
                <div class="action-buttons">
                    <a href="{{ $backupDownloadUrl }}" class="button-secondary">📥 Descargar Respaldo</a>
                </div>
            </div>

            <div class="step">
                <strong>OPCIÓN 3: Restaurar Posteriormmente</strong><br>
                <p>Después de la eliminación, podrás importar el archivo de respaldo para restaurar tu cuenta 
                en cualquier momento. Todos tus datos estarán disponibles.</p>
            </div>
        </div>

        <div class="info-section">
            <h3>❌ Después de la Eliminación</h3>
            <p>Una vez que tu cuenta sea eliminada:</p>
            <ul>
                <li>Tu empresa desaparecerá del sistema</li>
                <li>No podrás acceder a tu panel de control</li>
                <li>Solo tendrás acceso a tu archivo de respaldo descargado</li>
                <li>Podrás restaurar tu cuenta importando el respaldo</li>
            </ul>
        </div>

        <div class="action-buttons" style="margin: 40px 0;">
            <p><strong>ELIGE UNA ACCIÓN AHORA:</strong></p>
            <a href="{{ $reactivationUrl }}" class="button-secondary">Reactivar Cuenta</a>
            <a href="{{ $backupDownloadUrl }}" class="button-secondary">Descargar Respaldo</a>
        </div>

        <div class="critical-box">
            <strong>📞 ¿Necesitas Ayuda?</strong><br>
            Si crees que esto es un error o necesitas más tiempo, contacta a nuestro equipo de soporte inmediatamente.
        </div>

        <div class="footer">
            <p><strong>FECHA DE ELIMINACIÓN PROGRAMADA:</strong> {{ now()->addDays($daysRemaining)->format('d/m/Y às H:i') }}</p>
            <p>Este es un mensaje automático crítico del sistema de facturación electrónica.</p>
            <p>© {{ now()->year }} Firma Electrónica. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
