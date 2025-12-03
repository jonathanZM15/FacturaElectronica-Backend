<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerta de Seguridad</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .content {
            padding: 40px 30px;
        }
        .alert-box {
            background: #fef2f2;
            border-left: 4px solid #dc2626;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .alert-box h2 {
            margin: 0 0 10px 0;
            color: #991b1b;
            font-size: 18px;
        }
        .alert-box p {
            margin: 5px 0;
            color: #7f1d1d;
        }
        .info-grid {
            display: grid;
            gap: 15px;
            margin: 25px 0;
        }
        .info-item {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #6366f1;
        }
        .info-label {
            font-weight: 600;
            color: #374151;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-value {
            color: #1f2937;
            font-size: 16px;
            margin-top: 5px;
        }
        .security-tips {
            background: #eff6ff;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        .security-tips h3 {
            margin: 0 0 15px 0;
            color: #1e40af;
            font-size: 16px;
        }
        .security-tips ul {
            margin: 0;
            padding-left: 20px;
        }
        .security-tips li {
            color: #1e3a8a;
            margin: 8px 0;
        }
        .footer {
            background: #f9fafb;
            padding: 20px 30px;
            text-align: center;
            color: #6b7280;
            font-size: 13px;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">⚠️</div>
            <h1>Alerta de Seguridad</h1>
        </div>
        
        <div class="content">
            <p>Hola <strong>{{ $user->nombres }} {{ $user->apellidos }}</strong>,</p>
            
            <div class="alert-box">
                <h2>🔒 Intentos de acceso sospechosos detectados</h2>
                <p>Hemos detectado múltiples intentos fallidos de inicio de sesión en tu cuenta.</p>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">📧 Cuenta afectada</div>
                    <div class="info-value">{{ $user->email }}</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">🔢 Número de intentos</div>
                    <div class="info-value">{{ $attemptCount }} intentos fallidos</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">🌐 Dirección IP</div>
                    <div class="info-value">{{ $ipAddress }}</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">💻 Dispositivo</div>
                    <div class="info-value">{{ $deviceInfo }}</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">🕐 Fecha y Hora</div>
                    <div class="info-value">{{ $timestamp }}</div>
                </div>
            </div>

            <div class="security-tips">
                <h3>🛡️ Recomendaciones de seguridad</h3>
                <ul>
                    <li>Si <strong>reconoces esta actividad</strong>, puedes ignorar este mensaje.</li>
                    <li>Si <strong>NO reconoces estos intentos</strong>, te recomendamos:
                        <ul>
                            <li>Cambiar tu contraseña inmediatamente</li>
                            <li>Verificar los dispositivos con acceso a tu cuenta</li>
                            <li>Contactar al administrador del sistema si persisten los intentos</li>
                        </ul>
                    </li>
                    <li>Tu cuenta quedará <strong>bloqueada por 10 minutos</strong> después de 5 intentos fallidos.</li>
                </ul>
            </div>

            <p style="color: #6b7280; font-size: 14px; margin-top: 30px;">
                Esta es una notificación automática del sistema de seguridad de <strong>Máximo Facturas</strong>. 
                Monitorea continuamente tu cuenta para proteger tu información.
            </p>
        </div>
        
        <div class="footer">
            <p><strong>Máximo Facturas</strong></p>
            <p>Sistema de Facturación Electrónica</p>
            <p style="margin-top: 10px;">Este es un correo automático, por favor no responder.</p>
        </div>
    </div>
</body>
</html>
