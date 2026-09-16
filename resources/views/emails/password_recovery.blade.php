<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperación de contraseña - Máximo Facturas</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 20px;">
        <tr>
            <td align="center">
                <!-- Contenedor principal -->
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);">
                    <!-- Header con logo -->
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px;">
                            <h1 style="color: #ffffff; font-size: 32px; font-weight: 700; margin: 0; letter-spacing: 1px;">MÁXIMO FACTURAS</h1>
                            <p style="color: rgba(255, 255, 255, 0.9); font-size: 14px; margin: 8px 0 0 0;">Sistema de Facturación Electrónica</p>
                        </td>
                    </tr>
                    
                    <!-- Contenido principal -->
                    <tr>
                        <td style="padding: 40px 30px; color: #333333;">
                            <h1 style="font-size: 24px; font-weight: 600; color: #1a202c; margin: 0 0 20px 0;">¡Hola, {{ $user->name ?? 'Usuario' }}!</h1>
                            
                            <p style="font-size: 16px; color: #4a5568; margin: 0 0 15px 0; line-height: 1.8;">
                                Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en <strong>Máximo Facturas</strong>.
                            </p>

                            <p style="font-size: 16px; color: #4a5568; margin: 0 0 15px 0; line-height: 1.8;">
                                Para crear una nueva contraseña segura, haz clic en el siguiente botón:
                            </p>

                            <!-- Botón principal -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 35px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $url }}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; margin-bottom: 12px; width: 80%;">💻 Restablecer en la Web</a>
                                        <br>
                                        <a href="{{ str_replace(config('app.frontend_url', 'http://localhost:3000'), 'facturatesis:/', $url) }}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #059669 0%, #10B981 100%); color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; width: 80%;">📱 Restablecer en la App Móvil</a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Información adicional -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 25px 0;">
                                <tr>
                                    <td style="background: #f7fafc; border-left: 4px solid #667eea; padding: 15px 20px; border-radius: 4px;">
                                        <p style="font-size: 14px; color: #4a5568; margin: 0;">
                                            <strong>⏰ Importante:</strong> Este enlace es válido por un tiempo limitado por razones de seguridad.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size: 16px; color: #4a5568; margin: 0 0 15px 0; line-height: 1.8;">
                                Si no solicitaste este cambio, puedes ignorar este correo de forma segura. Tu contraseña actual no será modificada.
                            </p>

                            <!-- Enlace alternativo -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 25px 0; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                                <tr>
                                    <td>
                                        <p style="font-size: 13px; color: #718096; margin: 0 0 10px 0;"><strong>¿El botón no funciona?</strong> Copia y pega el siguiente enlace en tu navegador:</p>
                                        <div style="word-break: break-all; font-size: 12px; color: #667eea; background: #f7fafc; padding: 10px; border-radius: 4px;">{{ $url }}</div>
                                    </td>
                                </tr>
                            </table>

                            <!-- Aviso de seguridad -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 20px;">
                                <tr>
                                    <td style="font-size: 12px; color: #a0aec0; padding: 15px; background: #edf2f7; border-radius: 6px;">
                                        <strong>🛡️ Consejo de seguridad:</strong> Nunca compartas tu contraseña con nadie. Máximo Facturas nunca te pedirá tu contraseña por correo electrónico o teléfono.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background: #f7fafc; padding: 30px; border-top: 1px solid #e2e8f0;">
                            <p style="font-size: 14px; font-weight: 600; color: #667eea; margin: 0 0 5px 0;">Máximo Facturas</p>
                            <p style="font-size: 14px; color: #718096; margin: 0 0 5px 0;">Sistema de Facturación Electrónica</p>
                            <p style="font-size: 12px; color: #718096; margin: 15px 0 0 0;">© {{ date('Y') }} Máximo Facturas. Todos los derechos reservados.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
