<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📩 Confirmación de cuenta en Máximo Facturas</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f4f4f4;">
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
                            <h1 style="font-size: 24px; font-weight: 600; color: #1a202c; margin: 0 0 20px 0;">¡Bienvenido/a, {{ $user->nombres ?? 'Usuario' }}! 👋😊</h1>
                            
                            <p style="font-size: 16px; color: #4a5568; margin: 0 0 15px 0; line-height: 1.8;">
                                Su cuenta ha sido creada exitosamente en <strong>Máximo Facturas</strong> 🎉
                            </p>

                            <p style="font-size: 16px; color: #4a5568; margin: 0 0 15px 0; line-height: 1.8;">
                                Para activar su cuenta y comenzar a utilizar todas las funcionalidades del sistema, es necesario verificar su dirección de correo electrónico 📧.
                            </p>

                            <p style="font-size: 16px; color: #4a5568; margin: 0 0 25px 0; line-height: 1.8; font-weight: 600;">
                                Antes de continuar, por favor verifique que sus datos sean correctos:
                            </p>

                            <!-- Datos de la cuenta -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0; background: #f7fafc; border-radius: 8px; padding: 20px;">
                                <tr>
                                    <td style="padding: 8px 0; font-size: 15px; color: #4a5568;">
                                        <strong>🪪 Número de cédula:</strong> {{ $user->cedula ?? 'N/A' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; font-size: 15px; color: #4a5568;">
                                        <strong>👤 Nombre:</strong> {{ $user->nombres ?? 'N/A' }} {{ $user->apellidos ?? 'N/A' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; font-size: 15px; color: #4a5568;">
                                        <strong>📧 Correo electrónico:</strong> {{ $user->email ?? 'N/A' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; font-size: 15px; color: #4a5568;">
                                        <strong>🧑‍💻 Nombre de usuario:</strong> {{ $user->username ?? 'N/A' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; font-size: 15px; color: #4a5568;">
                                        <strong>🛡️ Rol asignado:</strong> {{ $user->role ?? 'N/A' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; font-size: 15px; color: #4a5568;">
                                        <strong>🏢 Emisor asociado:</strong> {{ $user->issuer_name ?? 'N/A' }}
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size: 15px; color: #4a5568; margin: 25px 0 0 0;">
                                Si la información es correcta, por favor proceda con la confirmación:
                            </p>

                            <!-- Botón principal -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $url }}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; transition: transform 0.3s ease;">👉 Verificar mi correo electrónico</a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Información adicional -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 25px 0;">
                                <tr>
                                    <td style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px 20px; border-radius: 4px;">
                                        <p style="font-size: 14px; color: #92400e; margin: 0;">
                                            <strong>⚠️ Importante:</strong> Este enlace es válido por 24 horas. Una vez verificada su cuenta, recibirá otro correo para establecer su contraseña personalizada 🔐.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size: 14px; color: #718096; margin: 20px 0 0 0;">
                                Si usted no solicitó la creación de esta cuenta, puede ignorar este mensaje de forma segura.
                            </p>

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
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f7fafc; padding: 30px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p style="font-size: 16px; font-weight: 600; color: #1a202c; margin: 0 0 8px 0;">Máximo Facturas</p>
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
