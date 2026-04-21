<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚠️ Intentos fallidos de acceso a su cuenta en Máximo Facturas</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: linear-gradient(135deg, #ef4444 0%, #991b1b 100%); padding: 40px 20px;">
        <tr>
            <td align="center">
                <!-- Contenedor principal -->
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);">
                    <!-- Header con logo -->
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, #ef4444 0%, #991b1b 100%); padding: 40px 30px;">
                            <h1 style="color: #ffffff; font-size: 32px; font-weight: 700; margin: 0; letter-spacing: 1px;">⚠️ ALERTA DE SEGURIDAD</h1>
                            <p style="color: rgba(255, 255, 255, 0.9); font-size: 14px; margin: 8px 0 0 0;">Máximo Facturas</p>
                        </td>
                    </tr>
                    
                    <!-- Contenido principal -->
                    <tr>
                        <td style="padding: 40px 30px; color: #333333;">
                            <h1 style="font-size: 24px; font-weight: 600; color: #1a202c; margin: 0 0 20px 0;">Estimado/a {{ $user->nombres ?? 'Usuario' }} 👋</h1>
                            
                            <p style="font-size: 16px; color: #4a5568; margin: 0 0 15px 0; line-height: 1.8;">
                                Hemos detectado <strong>múltiples intentos fallidos</strong> de inicio de sesión en su cuenta de <strong>Máximo Facturas</strong> 🔐.
                            </p>

                            <p style="font-size: 16px; color: #4a5568; margin: 0 0 25px 0; line-height: 1.8;">
                                Por motivos de seguridad, su acceso ha sido bloqueado temporalmente durante 10 minutos tras alcanzar el límite permitido de intentos.
                            </p>

                            <!-- Detalle del evento -->
                            <p style="font-size: 15px; color: #4a5568; margin: 25px 0 15px 0; font-weight: 600;">
                                📌 Detalle del evento:
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 15px 0; background: #fef3c7; border-radius: 8px; padding: 20px;">
                                <tr>
                                    <td style="padding: 8px 0; font-size: 15px; color: #92400e;">
                                        • <strong>Intentos fallidos consecutivos:</strong> {{ $attempts ?? '5' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; font-size: 15px; color: #92400e;">
                                        • <strong>Fecha y hora:</strong> {{ $date_time ?? date('d/m/Y H:i') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; font-size: 15px; color: #92400e;">
                                        • <strong>Dirección IP:</strong> {{ $ip_address ?? 'N/A' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; font-size: 15px; color: #92400e;">
                                        • <strong>Dispositivo / Navegador:</strong> {{ $device ?? 'N/A' }}
                                    </td>
                                </tr>
                            </table>

                            <!-- Acciones recomendadas -->
                            <p style="font-size: 15px; color: #4a5568; margin: 25px 0 15px 0; font-weight: 600;">
                                Si usted realizó estos intentos, podrá volver a acceder una vez finalizado el tiempo de bloqueo.
                            </p>

                            <!-- Advertencia -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 25px 0;">
                                <tr>
                                    <td style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px 20px; border-radius: 4px;">
                                        <p style="font-size: 14px; color: #7f1d1d; margin: 0 0 8px 0;">
                                            <strong>⚠️ Si no reconoce esta actividad, le recomendamos:</strong>
                                        </p>
                                        <p style="font-size: 14px; color: #7f1d1d; margin: 8px 0 0 0;">
                                            • Acceder directamente a la plataforma y cambiar su contraseña
                                        </p>
                                        <p style="font-size: 14px; color: #7f1d1d; margin: 4px 0 0 0;">
                                            • No compartir sus credenciales con terceros
                                        </p>
                                        <p style="font-size: 14px; color: #7f1d1d; margin: 4px 0 0 0;">
                                            • Contactar con el administrador de su cuenta si detecta actividad sospechosa
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Consejo de seguridad -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 25px 0;">
                                <tr>
                                    <td style="background: #f0fdf4; border-left: 4px solid #22c55e; padding: 15px 20px; border-radius: 4px;">
                                        <p style="font-size: 14px; color: #166534; margin: 0 0 8px 0;">
                                            🔒 <strong>Consejo de seguridad:</strong> Máximo Facturas nunca le solicitará su contraseña por correo electrónico ni le enviará enlaces para cambiarla sin que usted lo haya solicitado previamente.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f7fafc; padding: 30px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p style="font-size: 16px; font-weight: 600; color: #1a202c; margin: 0 0 8px 0;">Máximo Facturas 💼</p>
                            <p style="font-size: 13px; color: #718096; margin: 0 0 8px 0;">Sistema de Facturación Electrónica</p>
                            <p style="font-size: 12px; color: #a0aec0; margin: 0;">© {{ now()->year }} Máximo Facturas. Todos los derechos reservados.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
