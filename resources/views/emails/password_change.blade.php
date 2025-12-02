<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambia tu contraseña - Máximo Facturas</title>
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
                            <h1 style="font-size: 24px; font-weight: 600; color: #1a202c; margin: 0 0 20px 0;">¡Tu cuenta está activada, {{ $user->nombres ?? 'Usuario' }}! 🎉</h1>
                            
                            <p style="font-size: 16px; color: #4a5568; margin: 0 0 15px 0; line-height: 1.8;">
                                Has verificado exitosamente tu correo electrónico en <strong>Máximo Facturas</strong>.
                            </p>

                            <p style="font-size: 16px; color: #4a5568; margin: 0 0 15px 0; line-height: 1.8;">
                                Para comenzar a usar el sistema, necesitas establecer tu contraseña personalizada. Haz clic en el siguiente botón:
                            </p>

                            <!-- Botón principal -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 35px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $url }}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px;">🔐 Establecer mi contraseña</a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Información adicional -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 25px 0;">
                                <tr>
                                    <td style="background: #f7fafc; border-left: 4px solid #667eea; padding: 15px 20px; border-radius: 4px;">
                                        <p style="font-size: 14px; color: #4a5568; margin: 0;">
                                            <strong>⏰ Importante:</strong> Este enlace es válido por 48 horas. Después de establecer tu contraseña, podrás acceder al sistema con tu usuario y contraseña.
                                        </p>
                                    </td>
                                </tr>
                            </table>

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
                                        <strong>🛡️ Consejo de seguridad:</strong> Elige una contraseña segura que contenga al menos 8 caracteres, incluyendo letras mayúsculas, minúsculas, números y símbolos. Nunca compartas tu contraseña con nadie.
                                    </td>
                                </tr>
                            </table>

                            <!-- Información de acceso -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 20px;">
                                <tr>
                                    <td style="font-size: 14px; color: #4a5568; line-height: 1.6;">
                                        <p style="margin: 0 0 10px 0;"><strong>Recuerda tus datos de acceso:</strong></p>
                                        <ul style="margin: 0; padding-left: 20px;">
                                            <li>Usuario: <strong>{{ $user->username ?? 'N/A' }}</strong></li>
                                            <li>Email: <strong>{{ $user->email }}</strong></li>
                                        </ul>
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
