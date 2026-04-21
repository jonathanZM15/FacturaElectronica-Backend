# 📧 Actualización de Formatos de Correos Electrónicos - Máximo Facturas

**Fecha:** 17 de abril de 2026
**Versión:** 2.0
**Ubicación:** `Factura-Backend/resources/views/emails/`

---

## 📋 Resumen de Cambios

Se han actualizado y creado **9 plantillas de correo electrónico** con nuevo diseño profesional, mejores mensajes y emojis para mayor claridad.

---

## ✅ Correos Creados/Actualizados

### 1. 📩 Confirmación de Cuenta
- **Archivo:** `account_confirmation.blade.php`
- **Nombre Técnico:** `EMAIL_ACCOUNT_CONFIRMATION`
- **Asunto:** 📩 Confirmación de cuenta en Máximo Facturas
- **Propósito:** Verificar identidad y datos al crear una cuenta
- **Validez del enlace:** 24 horas
- **Gradiente:** Púrpura (#667eea - #764ba2)
- **Variables:**
  - `{{ $user->nombres }}`
  - `{{ $user->cedula }}`
  - `{{ $user->nombres }} {{ $user->apellidos }}`
  - `{{ $user->email }}`
  - `{{ $user->username }}`
  - `{{ $user->role }}`
  - `{{ $user->issuer_name }}`
  - `{{ $url }}`

---

### 2. 🔐 Establecimiento de Contraseña
- **Archivo:** `password_setup.blade.php`
- **Nombre Técnico:** `EMAIL_PASSWORD_SETUP`
- **Asunto:** 🔐 Establecimiento de contraseña para acceder a Máximo Facturas
- **Propósito:** Permitir al usuario crear su contraseña personalizada
- **Validez del enlace:** 48 horas
- **Gradiente:** Cian (#06b6d4 - #0891b2)
- **Incluye:** Consejos de seguridad, datos de acceso

---

### 3. ✅ Reactivación de Cuenta
- **Archivo:** `account_reactivated.blade.php`
- **Nombre Técnico:** `EMAIL_ACCOUNT_REACTIVATED`
- **Asunto:** ✅ Reactivación de su cuenta en Máximo Facturas
- **Propósito:** Notificar reactivación de cuenta
- **Gradiente:** Verde (#10b981 - #059669)
- **Tipo:** Notificación simples (sin acciones)

---

### 4. ⚠️ Suspensión de Cuenta
- **Archivo:** `account_suspended.blade.php`
- **Nombre Técnico:** `EMAIL_ACCOUNT_SUSPENDED`
- **Asunto:** ⚠️ Suspensión de su cuenta en Máximo Facturas
- **Propósito:** Notificar suspensión temporal de cuenta
- **Gradiente:** Ámbar (#f59e0b - #d97706)
- **Tipo:** Alerta de acción

---

### 5. 🚫 Desactivación de Cuenta
- **Archivo:** `account_deactivated.blade.php`
- **Nombre Técnico:** `EMAIL_ACCOUNT_DEACTIVATED`
- **Asunto:** 🚫 Desactivación de su cuenta en Máximo Facturas
- **Propósito:** Notificar desactivación permanente de cuenta
- **Gradiente:** Rojo (#ef4444 - #dc2626)
- **Tipo:** Alerta crítica

---

### 6. 🔐 Restablecimiento de Contraseña
- **Archivo:** `password_reset.blade.php`
- **Nombre Técnico:** `EMAIL_PASSWORD_RESET`
- **Asunto:** 🔐 Restablecimiento de contraseña en Máximo Facturas
- **Propósito:** Permitir recuperación de contraseña olvidada
- **Validez del enlace:** 48 horas
- **Gradiente:** Púrpura (#8b5cf6 - #6d28d9)
- **Incluye:** Consejos de seguridad, confirmación de no solicitud

---

### 7. 🔐 Verificación para Reactivación
- **Archivo:** `account_reactivation_verify.blade.php`
- **Nombre Técnico:** `EMAIL_ACCOUNT_REACTIVATION_VERIFY`
- **Asunto:** 🔐 Verificación para reactivar su cuenta en Máximo Facturas
- **Propósito:** Verificar identidad antes de reactivar cuenta
- **Validez del enlace:** 48 horas
- **Gradiente:** Azul (#3b82f6 - #1d4ed8)
- **Incluye:** Advertencia si no reconoce solicitud

---

### 8. ⚠️ Notificación de Cambio de Correo
- **Archivo:** `email_change_notice.blade.php`
- **Nombre Técnico:** `EMAIL_EMAIL_CHANGE_NOTICE`
- **Asunto:** ⚠️ Solicitud de cambio de correo en Máximo Facturas
- **Propósito:** Notificar cambio de correo solicitado
- **Gradiente:** Rosa (#ec4899 - #be185d)
- **Incluye:** Nuevo correo, advertencia de seguridad
- **Variables:**
  - `{{ $user->nombres }}`
  - `{{ $new_email }}`

---

### 9. 📧 Confirmación de Cambio de Correo
- **Archivo:** `email_change_confirm.blade.php`
- **Nombre Técnico:** `EMAIL_EMAIL_CHANGE_CONFIRM`
- **Asunto:** 📧 Confirmación de cambio de correo en Máximo Facturas
- **Propósito:** Confirmar nuevo correo electrónico
- **Validez del enlace:** 48 horas
- **Gradiente:** Teal (#14b8a6 - #0d9488)
- **Incluye:** Enlace de confirmación, advertencia de seguridad
- **Variables:**
  - `{{ $user->nombres }}`
  - `{{ $url }}`

---

### 10. ⚠️ Alerta de Intentos Fallidos de Acceso
- **Archivo:** `login_attempts_alert.blade.php`
- **Nombre Técnico:** `EMAIL_LOGIN_ATTEMPTS_ALERT`
- **Asunto:** ⚠️ Intentos fallidos de acceso a su cuenta en Máximo Facturas
- **Propósito:** Alertar sobre intentos fallidos y bloqueo temporal
- **Gradiente:** Rojo oscuro (#ef4444 - #991b1b)
- **Incluye:** Detalles de evento, recomendaciones de seguridad
- **Variables:**
  - `{{ $user->nombres }}`
  - `{{ $attempts }}`
  - `{{ $date_time }}`
  - `{{ $ip_address }}`
  - `{{ $device }}`

---

## 🎨 Características de Diseño

Todos los correos incluyen:

✅ **Diseño Responsivo**
- Compatible con móviles y escritorio
- Estructura de tabla para máxima compatibilidad

✅ **Gradientes Profesionales**
- Cada tipo de correo tiene su gradiente único
- Headers personalizados por contexto

✅ **Emojis Contextuales**
- Mayor claridad y engagement
- Mejoran la experiencia visual

✅ **Cajas de Información Codificadas por Color**
- ⚠️ Amarillo: Advertencias importantes
- ℹ️ Azul: Información adicional
- ✅ Verde: Consejos de seguridad
- 🔴 Rojo: Alertas críticas

✅ **Botones de Acción**
- Gradientes que coinciden con headers
- Estados de hover definidos
- Enlaces alternativos para errores

✅ **Footer Consistente**
- Branding de Máximo Facturas
- Copyright con año dinámico
- Información de sistema

---

## 📝 Variables Comunes

| Variable | Descripción |
|----------|-------------|
| `{{ $user->nombres }}` | Primer nombre del usuario |
| `{{ $user->apellidos }}` | Apellidos del usuario |
| `{{ $user->email }}` | Correo electrónico |
| `{{ $user->username }}` | Nombre de usuario |
| `{{ $user->cedula }}` | Número de cédula |
| `{{ $user->role }}` | Rol asignado |
| `{{ $user->issuer_name }}` | Nombre del emisor |
| `{{ $url }}` | URL del enlace de acción |
| `{{ $new_email }}` | Nuevo correo (para cambios) |
| `{{ now()->year }}` | Año actual |

---

## 🔐 Consejos de Seguridad Incluidos

Todos los correos incluyen avisos de seguridad:
- Nunca compartir contraseña
- Máximo Facturas no solicita contraseña por correo
- Verificar URLs antes de hacer clic
- Contactar a soportet si no reconoce solicitudes

---

## 📦 Próximos Pasos

Necesitas crear/actualizar las clases PHP Mailable para enviar estos correos:

- [ ] `AccountConfirmationMail.php`
- [ ] `PasswordSetupMail.php`
- [ ] `AccountReactivatedMail.php`
- [ ] `AccountSuspendedMail.php`
- [ ] `AccountDeactivatedMail.php`
- [ ] `PasswordResetMail.php`
- [ ] `AccountReactivationVerifyMail.php`
- [ ] `EmailChangeNoticeMail.php`
- [ ] `EmailChangeConfirmMail.php`
- [ ] `LoginAttemptsAlertMail.php`

---

## 📞 Soporte

Ubicación de archivos:
```
Factura-Backend/
└── resources/
    └── views/
        └── emails/
            ├── account_confirmation.blade.php
            ├── password_setup.blade.php
            ├── account_reactivated.blade.php
            ├── account_suspended.blade.php
            ├── account_deactivated.blade.php
            ├── password_reset.blade.php
            ├── account_reactivation_verify.blade.php
            ├── email_change_notice.blade.php
            ├── email_change_confirm.blade.php
            └── login_attempts_alert.blade.php
```

---

**Versión Actualizada:** ✅ Completada
**Calidad de Diseño:** Premium ✨
**Compatibilidad:** 100% HTML estándar
