<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📧 Confirmación de cambio de correo en Máximo Facturas</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); padding: 40px 20px;">
        <tr>
            <td align="center">
                <!-- Contenedor principal -->
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);">
                    <!-- Header con logo -->
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); padding: 40px 30px;">
                            <h1 style="color: #ffffff; font-size: 32px; font-weight: 700; margin: 0; letter-spacing: 1px;">MÁXIMO FACTURAS</h1>
                            <p style="color: rgba(255, 255, 255, 0.9); font-size: 14px; margin: 8px 0 0 0;">Sistema de Facturación Electrónica</p>
                        </td>
                    </tr>
                    
                    <!-- Contenido principal -->
                    <tr>
                        <td style="padding: 40px 30px; color: #333333;">
                            <h1 style="font-size: 24px; font-weight: 600; color: #1a202c; margin: 0 0 20px 0;">Estimado/a {{ $user->nombres ?? 'Usuario' }} 👋</h1>
                            
                            <p style="font-size: 16px; color: #4a5568; margin: 0 0 15px 0; line-height: 1.8;">
                                Se ha solicitado asociar esta dirección de correo electrónico a una cuenta de <strong>Máximo Facturas</strong> 📧.
                            </p>

                            <p style="font-size: 16px; color: #4a5568; margin: 0 0 25px 0; line-height: 1.8;">
                                Para confirmar este cambio, haga clic en el siguiente botón:
                            </p>

                            <!-- Botón principal -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $url }}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; transition: transform 0.3s ease;">👉 Confirmar cambio de correo electrónico</a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Información adicional -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 25px 0;">
                                <tr>
                                    <td style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px 20px; border-radius: 4px;">
                                        <p style="font-size: 14px; color: #92400e; margin: 0;">
                                            <strong>⚠️ Importante:</strong> Este enlace es válido por 48 horas.
                                        </p>
                                        <p style="font-size: 14px; color: #92400e; margin: 8px 0 0 0;">
                                            Si usted no reconoce esta solicitud, puede ignorar este mensaje de forma segura. No se realizará ningún cambio en la cuenta.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Enlace alternativo -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 25px 0;">
                                <tr>
                                    <td style="background: #f0f9ff; border-left: 4px solid #3b82f6; padding: 15px 20px; border-radius: 4px;">
                                        <p style="font-size: 13px; color: #1e40af; margin: 0 0 8px 0;">
                                            <strong>❓ ¿El botón no funciona?</strong> Copie y pegue el siguiente enlace en su navegador:
                                        </p>
                                        <p style="font-size: 12px; color: #1e40af; margin: 0; word-break: break-all;">
                                            <a href="{{ $url }}" style="color: #1e40af; text-decoration: none;">{{ $url }}</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Consejo de seguridad -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 25px 0;">
                                <tr>
                                    <td style="background: #f0fdf4; border-left: 4px solid #22c55e; padding: 15px 20px; border-radius: 4px;">
                                        <p style="font-size: 14px; color: #166534; margin: 0;">
                                            🔒 <strong>Consejo de seguridad:</strong> Nunca comparta su información de acceso. Máximo Facturas no le solicitará contraseñas por correo electrónico ni por teléfono.
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
