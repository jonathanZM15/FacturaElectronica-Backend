<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚠️ Solicitud de cambio de correo en Máximo Facturas</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: linear-gradient(135deg, #ec4899 0%, #be185d 100%); padding: 40px 20px;">
        <tr>
            <td align="center">
                <!-- Contenedor principal -->
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);">
                    <!-- Header con logo -->
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, #ec4899 0%, #be185d 100%); padding: 40px 30px;">
                            <h1 style="color: #ffffff; font-size: 32px; font-weight: 700; margin: 0; letter-spacing: 1px;">MÁXIMO FACTURAS</h1>
                            <p style="color: rgba(255, 255, 255, 0.9); font-size: 14px; margin: 8px 0 0 0;">Sistema de Facturación Electrónica</p>
                        </td>
                    </tr>
                    
                    <!-- Contenido principal -->
                    <tr>
                        <td style="padding: 40px 30px; color: #333333;">
                            <h1 style="font-size: 24px; font-weight: 600; color: #1a202c; margin: 0 0 20px 0;">Estimado/a {{ $user->nombres ?? 'Usuario' }} 👋</h1>
                            
                            <p style="font-size: 16px; color: #4a5568; margin: 0 0 15px 0; line-height: 1.8;">
                                Le informamos que se ha solicitado un cambio de correo electrónico para su cuenta en <strong>Máximo Facturas</strong> 📧.
                            </p>

                            <!-- Nuevo correo -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0; background: #f7fafc; border-radius: 8px; padding: 20px;">
                                <tr>
                                    <td style="padding: 8px 0; font-size: 15px; color: #4a5568;">
                                        <strong>🔄 Nuevo correo solicitado:</strong> {{ $new_email ?? 'N/A' }}
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size: 16px; color: #4a5568; margin: 25px 0 0 0; line-height: 1.8;">
                                Si usted realizó esta solicitud, puede continuar con el proceso desde el correo electrónico nuevo asociado a su cuenta.
                            </p>

                            <!-- Advertencia importante -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 25px 0;">
                                <tr>
                                    <td style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px 20px; border-radius: 4px;">
                                        <p style="font-size: 14px; color: #7f1d1d; margin: 0;">
                                            <strong>⚠️ Importante:</strong> Si usted no solicitó este cambio, le recomendamos comunicarse de inmediato con el emisor responsable asociado a su cuenta o con soporte técnico para proteger su acceso 🚫.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Consejo de seguridad -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 25px 0;">
                                <tr>
                                    <td style="background: #f0fdf4; border-left: 4px solid #22c55e; padding: 15px 20px; border-radius: 4px;">
                                        <p style="font-size: 14px; color: #166534; margin: 0;">
                                            🔒 <strong>Consejo de seguridad:</strong> Máximo Facturas nunca le solicitará su contraseña por correo electrónico ni por teléfono.
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
