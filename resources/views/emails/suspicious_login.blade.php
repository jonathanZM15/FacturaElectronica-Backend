<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerta de Seguridad</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f4f8;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f0f4f8; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.15);">
                    
                    <!-- HEADER -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #dc2626 0%, #7f1d1d 100%); padding: 50px 40px; text-align: center;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <div style="width: 90px; height: 90px; background: rgba(255,255,255,0.15); border-radius: 50%; margin: 0 auto 20px; line-height: 90px; font-size: 45px;">
                                            🚨
                                        </div>
                                        <h1 style="color: white; margin: 0; font-size: 32px; font-weight: 800; letter-spacing: -1px;">
                                            ¡ALERTA DE SEGURIDAD!
                                        </h1>
                                        <p style="color: rgba(255,255,255,0.9); margin: 15px 0 0; font-size: 16px;">
                                            Se detectó actividad sospechosa en tu cuenta
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- CONTENIDO -->
                    <tr>
                        <td style="padding: 50px 45px;">
                            
                            <!-- Saludo -->
                            <p style="font-size: 18px; color: #1f2937; margin: 0 0 30px;">
                                Hola <strong style="color: #dc2626;">{{ $user->nombres }} {{ $user->apellidos }}</strong>,
                            </p>

                            <!-- Alerta Principal -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%); border-radius: 16px; margin-bottom: 35px; border-left: 6px solid #dc2626;">
                                <tr>
                                    <td style="padding: 30px;">
                                        <h2 style="color: #991b1b; margin: 0 0 15px; font-size: 22px; font-weight: 700;">
                                            ⚠️ Múltiples intentos de acceso fallidos
                                        </h2>
                                        <p style="color: #7f1d1d; margin: 0; font-size: 16px; line-height: 1.6;">
                                            Alguien ha intentado acceder a tu cuenta de <strong>Máximo Facturas</strong> usando credenciales incorrectas. 
                                            Por tu seguridad, hemos bloqueado temporalmente el acceso.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Detalles del Incidente -->
                            <h3 style="color: #374151; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; margin: 0 0 20px; padding-bottom: 15px; border-bottom: 3px solid #e5e7eb;">
                                📋 Detalles del Incidente
                            </h3>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 35px;">
                                <tr>
                                    <td style="padding: 18px 20px; background: #f9fafb; border-radius: 12px; margin-bottom: 12px;">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="color: #6b7280; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
                                                    📧 Cuenta afectada
                                                </td>
                                                <td align="right" style="color: #1f2937; font-size: 16px; font-weight: 600;">
                                                    {{ $user->email }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr><td style="height: 12px;"></td></tr>
                                <tr>
                                    <td style="padding: 18px 20px; background: #fef2f2; border-radius: 12px; border: 2px solid #fecaca;">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="color: #991b1b; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
                                                    🔢 Intentos fallidos
                                                </td>
                                                <td align="right" style="color: #dc2626; font-size: 24px; font-weight: 800;">
                                                    {{ $attemptCount }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr><td style="height: 12px;"></td></tr>
                                <tr>
                                    <td style="padding: 18px 20px; background: #f9fafb; border-radius: 12px;">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="color: #6b7280; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
                                                    🌐 Dirección IP
                                                </td>
                                                <td align="right" style="color: #1f2937; font-size: 16px; font-weight: 600; font-family: 'Courier New', monospace;">
                                                    {{ $ipAddress }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr><td style="height: 12px;"></td></tr>
                                <tr>
                                    <td style="padding: 18px 20px; background: #f9fafb; border-radius: 12px;">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="color: #6b7280; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
                                                    💻 Dispositivo
                                                </td>
                                                <td align="right" style="color: #1f2937; font-size: 15px; font-weight: 600;">
                                                    {{ $deviceInfo }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr><td style="height: 12px;"></td></tr>
                                <tr>
                                    <td style="padding: 18px 20px; background: #f9fafb; border-radius: 12px;">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="color: #6b7280; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
                                                    🕐 Fecha y hora (Ecuador)
                                                </td>
                                                <td align="right" style="color: #1f2937; font-size: 16px; font-weight: 600;">
                                                    {{ $timestamp }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Aviso de Bloqueo -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 16px; margin-bottom: 35px; border: 2px solid #f59e0b;">
                                <tr>
                                    <td style="padding: 25px; text-align: center;">
                                        <p style="color: #92400e; margin: 0; font-size: 18px; font-weight: 700;">
                                            ⏰ Tu cuenta ha sido bloqueada por <span style="color: #dc2626; font-size: 22px;">10 minutos</span>
                                        </p>
                                        <p style="color: #a16207; margin: 10px 0 0; font-size: 14px;">
                                            Después de este tiempo podrás intentar iniciar sesión nuevamente
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Si fuiste tú -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-radius: 16px; margin-bottom: 20px; border-left: 6px solid #10b981;">
                                <tr>
                                    <td style="padding: 25px;">
                                        <h3 style="color: #065f46; margin: 0 0 12px; font-size: 18px; font-weight: 700;">
                                            ✅ ¿Fuiste tú?
                                        </h3>
                                        <p style="color: #047857; margin: 0; font-size: 15px; line-height: 1.6;">
                                            No te preocupes, simplemente espera 10 minutos e intenta de nuevo con la contraseña correcta. 
                                            Puedes ignorar este mensaje.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Si NO fuiste tú -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%); border-radius: 16px; margin-bottom: 35px; border-left: 6px solid #dc2626;">
                                <tr>
                                    <td style="padding: 25px;">
                                        <h3 style="color: #991b1b; margin: 0 0 15px; font-size: 18px; font-weight: 700;">
                                            🚫 ¿NO reconoces esta actividad?
                                        </h3>
                                        <p style="color: #7f1d1d; margin: 0 0 15px; font-size: 15px; line-height: 1.6;">
                                            Esto podría significar que alguien está intentando acceder a tu cuenta sin autorización. Te recomendamos:
                                        </p>
                                        <ul style="color: #991b1b; margin: 0 0 20px; padding-left: 20px; font-size: 14px; line-height: 1.8;">
                                            <li>Cambia tu contraseña inmediatamente después del desbloqueo</li>
                                            <li>Verifica que tu correo electrónico esté seguro</li>
                                            <li>Revisa los dispositivos que tienen acceso a tu cuenta</li>
                                        </ul>
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td align="center">
                                                    <a href="https://wa.me/message/72PVPYUWIIPOG1" target="_blank" style="display: inline-block; background: linear-gradient(135deg, #25d366 0%, #128c7e 100%); color: white; text-decoration: none; padding: 16px 35px; border-radius: 50px; font-size: 16px; font-weight: 700; box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);">
                                                        💬 Contactar a Soporte por WhatsApp
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 40px; text-align: center;">
                            <h2 style="color: white; margin: 0 0 8px; font-size: 24px; font-weight: 800;">
                                Máximo Facturas
                            </h2>
                            <p style="color: rgba(255,255,255,0.7); margin: 0 0 25px; font-size: 14px;">
                                Sistema de Facturación Electrónica
                            </p>
                            <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 25px;">
                                <p style="color: rgba(255,255,255,0.5); margin: 0; font-size: 12px; line-height: 1.6;">
                                    Este es un correo automático generado por medidas de seguridad.<br>
                                    Por favor no responder a este mensaje.
                                </p>
                            </div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
