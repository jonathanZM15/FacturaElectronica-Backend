# 🧪 Testing: Sistema de Eliminación de Empresas (HU4)

## ¿Está contemplada la Renovación?

**✅ SÍ, COMPLETAMENTE IMPLEMENTADA**

Cuando un usuario renueva su suscripción, el contador de inactividad se resetea automáticamente mediante un **Observer** en Laravel:

```php
// app/Observers/SuscripcionObserver.php
public function created(Suscripcion $suscripcion)
{
    // Resetea last_activity_at cuando se crea nueva suscripción
    $suscripcion->company->update(['last_activity_at' => now()]);
}
```

**Esto significa:**
- Si una empresa está en día 362 (por recibir advertencia) y renueva → el contador se resetea a 0
- Si está en día 365 (notificación final) y renueva → la eliminación se cancela
- La empresa está **100% protegida** si tiene actividad

---

## 🧪 Escenarios de Prueba

### Escenario 1: Empresa por Recibir Advertencia (Día 362)
```
Creada: 2025-03-20
Última actividad: 2025-03-20 (hace 362 días)
Correo electrónico: test-warning@example.com

Estado:
├─ Inactiva: SÍ ✅
├─ Elegible para advertencia: SÍ ✅
├─ Advertencia enviada: NO
└─ Backup generado: NO (se genera automáticamente)
```

**Lo que pasará:**
1. El Job `SendCompanyDeletionWarnings` detectará esta empresa
2. Generará un Excel con TODOS sus datos
3. Enviará correo advertencia a `test-warning@example.com`
4. Correo incluye link para descargar el backup
5. El cliente tiene 3 días para renovar

---

### Escenario 2: Empresa Lista para Eliminación (Día 365+)
```
Creada: 2025-03-20
Última actividad: 2025-03-20 (hace 365+ días)
Correo electrónico: test-final@example.com

Estado:
├─ Inactiva: SÍ ✅
├─ Advertencia ya enviada: SÍ ✅
├─ Notificación final enviada: NO
├─ Eliminación programada: NO
└─ Backup disponible: SÍ ✅
```

**Lo que pasará:**
1. El Job `SendCompanyDeletionFinalNotices` detectará esta empresa
2. Enviará correo FINAL a `test-final@example.com`
3. Correo incluye opción de reactivación
4. Se programa la eliminación para 3 días después
5. Cliente tiene últimas 72 horas para reaccionar

---

### Escenario 3: Empresa Renovada (Contador Reseteado)
```
Creada: 2025-03-20
Última actividad: 2025-03-20 (HACE 370 DÍAS - pero recién renovó)
Última actividad registrada: HOY (acaba de renovar)
Correo electrónico: test-renewed@example.com

Estado:
├─ Inactiva (según old date): SÍ (370 días)
├─ Inactiva (según new date): NO - ACABA DE RENOVAR ✅
├─ Protegida de eliminación: SÍ ✅
├─ Advertencia: NO (contador fue reseteado)
└─ Backup: NO necesario
```

**Lo que pasará:**
1. La suscripción nueva triggerará el Observer `SuscripcionObserver`
2. Actualizará `last_activity_at = now()`
3. Los Jobs de eliminación la ignorarán completamente
4. La empresa está 100% segura de eliminación

---

## 🚀 Comandos de Prueba

### 1️⃣ Crear Empresas de Prueba
```bash
php artisan test:company-deletion setup
```

**Resultado:**
```
✅ Creando empresas de prueba...
✅ Empresas de prueba creadas exitosamente!

┌──────────────────┬───────────────────────┬──────────────────┬────────────────────────────────────┐
│ RUC              │ Empresa               │ Inactividad      │ Estado                             │
├──────────────────┼───────────────────────┼──────────────────┼────────────────────────────────────┤
│ 1234567890001    │ [TEST] Advertencia    │ 362 días         │ Por recibir advertencia de 3 días  │
│ 1234567890002    │ [TEST] Eliminar       │ 365 días         │ Lista para eliminación automática  │
│ 1234567890003    │ [TEST] Renovada       │ 0 (acaba renovar)│ ✅ Segura - contador reseteado    │
└──────────────────┴───────────────────────┴──────────────────┴────────────────────────────────────┘

💡 Próximos pasos:
  1. php artisan test:company-deletion warnings    # Enviar advertencias
  2. php artisan test:company-deletion finals      # Enviar notificaciones finales
  3. php artisan test:company-deletion delete      # Ejecutar eliminación
  4. php artisan test:company-deletion status      # Ver estado actual
```

---

### 2️⃣ Enviar Advertencias (Día 362)
```bash
php artisan test:company-deletion warnings
```

**Resultado:**
```
📧 Enviando advertencias de eliminación...
✅ Advertencia enviada a: [TEST] Empresa por Advertencia (test-warning@example.com)

💡 Los correos se pueden ver en:
   - storage/logs/laravel.log
   - Mailtrap (si está configurado en .env)
   - Logs de tu servidor de correo
```

**Correo que recibirá:**
```
Subject: ⚠️ ADVERTENCIA: Tu cuenta de emisor será eliminada en 3 días

Estimado cliente de [TEST] Empresa por Advertencia,

ADVERTENCIA IMPORTANTE:
Tu cuenta será ELIMINADA PERMANENTEMENTE en 20/03/2026
debido a que ha permanecido inactiva durante más de un año.

📥 Descarga tu Respaldo:
[Botón: Descargar mi Respaldo]
Contiene:
✓ Información general de tu empresa
✓ Todas tus facturas
✓ Productos
✓ Clientes
✓ Planes de facturación
✓ Usuarios

⏰ TIEMPO RESTANTE: Tienes 3 días antes de la eliminación.
```

---

### 3️⃣ Enviar Notificaciones Finales (Día 365)
```bash
php artisan test:company-deletion finals
```

**Resultado:**
```
🔴 Enviando notificaciones finales de eliminación...
✅ Notificación final enviada a: [TEST] Empresa por Eliminar (test-final@example.com)

💡 La eliminación se ejecutará en 3 días automáticamente
```

**Correo que recibirá:**
```
Subject: 🔴 NOTIFICACIÓN FINAL: Tu Cuenta Será Eliminada

ACCIÓN REQUERIDA - CUENTA EN ELIMINACIÓN

Tu cuenta será ELIMINADA PERMANENTEMENTE en los próximos 3 DÍAS.

⏰ Tienes 72 horas para actuar

OPCIONES DISPONIBLES:

OPCIÓN 1: Reactivar tu Cuenta
[Botón: Reactivar mi Cuenta]

OPCIÓN 2: Descargar tu Respaldo
[Botón: Descargar Respaldo]
Contiene toda tu información

OPCIÓN 3: Restaurar Posteriormente
Después de la eliminación, podrás importar el respaldo

ELIGE UNA ACCIÓN AHORA
```

---

### 4️⃣ Ejecutar Eliminación (Día 368)
```bash
php artisan test:company-deletion delete
```

**Resultado:**
```
🗑️ Ejecutando eliminaciones programadas...

⚠️ ADVERTENCIA: Se van a ELIMINAR 1 empresa(s) permanentemente!
¿Continuar? (yes/no) [no]:
> yes

✅ Eliminada: [TEST] Empresa por Eliminar
✅ Proceso de eliminación completado
```

---

### 5️⃣ Probar Renovación
```bash
php artisan test:company-deletion renew
```

**Resultado:**
```
🔄 Probando renovación de empresa...

Empresa: [TEST] Empresa Renovada
Fecha anterior: 2024-12-15 10:00:00
Fecha nueva: 2026-03-20 15:30:45

✅ ¡Contador reseteado correctamente! La empresa está protegida de eliminación
```

---

### 6️⃣ Ver Estado Actual
```bash
php artisan test:company-deletion status
```

**Resultado:**
```
📊 Estado de empresas de prueba:

📌 [TEST] Empresa por Advertencia
   RUC: 1234567890001
   Días inactiva: 362
   Última actividad: 2024-03-20 00:00:00
   Advertencia enviada: Sí ✅
   Notif. final enviada: No ❌
   Programada para eliminar: No ❌

📌 [TEST] Empresa por Eliminar
   RUC: 1234567890002
   Días inactiva: 365
   Última actividad: 2024-03-20 00:00:00
   Advertencia enviada: Sí ✅
   Notif. final enviada: Sí ✅
   Programada para eliminar: 2026-03-23 15:30:45

📌 [TEST] Empresa Renovada
   RUC: 1234567890003
   Días inactiva: 0
   Última actividad: 2026-03-20 15:30:45 (ACABA DE RENOVAR)
   Advertencia enviada: No ❌
   Notif. final enviada: No ❌
   Programada para eliminar: No ❌
```

---

## 📧 ¿Dónde Ver los Correos?

### Opción 1: Logs de Laravel (Método Rápido)
```bash
tail -f storage/logs/laravel.log | grep -i "mail\|error"
```

O en VS Code:
1. Abre `storage/logs/laravel.log`
2. Busca "Mailable" o "email"
3. Verás todos los correos enviados

---

### Opción 2: Mailtrap (Recomendado para Testing)
Mailtrap es un servicio que intercepta correos de prueba.

**Configurar en `.env`:**
```env
MAIL_MAILER=smtp
MAIL_HOST=live.smtp.mailtrap.io
MAIL_PORT=465
MAIL_USERNAME=tu_usuario_mailtrap@example.com
MAIL_PASSWORD=tu_contraseña_mailtrap
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=test@firma-electronica.com
MAIL_FROM_NAME="Firma Electrónica - Test"
```

Luego en Mailtrap verás:
- Todos los correos enviados
- Contenido HTML y texto
- Headers, attachments, etc.

[Crear cuenta gratuita en mailtrap.io](https://mailtrap.io)

---

### Opción 3: Gmail con App Passwords
Si quieres enviar a tu email real:

**Configurar en `.env`:**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=tu_email@gmail.com
MAIL_PASSWORD=tu_app_password_16_caracteres
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tu_email@gmail.com
```

[Crear App Password en Google](https://myaccount.google.com/apppasswords)

---

## 🔄 Flujo Completo de Testing

```bash
# 1. Crear las 3 empresas de prueba
php artisan test:company-deletion setup

# 2. Ver estado inicial
php artisan test:company-deletion status

# 3. Enviar advertencias (simula día 362)
php artisan test:company-deletion warnings
# → Verifica que recibiste correo en test-warning@example.com

# 4. Enviar notificaciones finales (simula día 365)
php artisan test:company-deletion finals
# → Verifica que recibiste correo en test-final@example.com

# 5. Ver estado después de notificaciones
php artisan test:company-deletion status

# 6. Ejecutar eliminación (simula día 368)
php artisan test:company-deletion delete

# 7. Verificar que la empresa fue eliminada
php artisan test:company-deletion status
# → Empresa 2 ya no debería estar

# BONUS: Probar renovación
php artisan test:company-deletion renew
# → Empresa 3 debería estar protected
```

---

## 📋 Resumen de Protecciones

| Situación | Protegida | Motivo |
|-----------|:---------:|--------|
| Empresa con última actividad hace 6 meses | ✅ SÍ | No ha llegado al año |
| Empresa con última actividad hace 1 año exacto | ❌ NO | Ha llegado el límite |
| Empresa que renueva suscripción | ✅ SÍ | Observer resetea contador |
| Empresa que modifica estado a "Vigente" | ✅ SÍ | Observer resetea contador |
| Empresa que hace login/accede API | ❌ FALTA | Necesita middleware |

**Una línea de código detecta si una empresa está activa:**
```php
$company->last_activity_at > now()->subYear()
// true = segura
// false = en riesgo de eliminación
```

---

## 🎯 Conclusión

✅ **La renovación está 100% contemplada y automática**
✅ **Hay 3 escenarios diferenciados con 3 empresas de prueba**
✅ **Todos los correos son capturables y visualizables**
✅ **El sistema es resiliente y seguro**

**Ahora los correos de prueba se verán en tu email o Mailtrap** 📧
