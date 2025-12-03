# Transiciones de Estado de Usuarios

## Descripción General

El sistema de estados de usuarios en Máximo Facturas controla el ciclo de vida de cada cuenta, desde su creación hasta su eventual baja o reactivación.

## Estados Disponibles

| Estado | Descripción |
|--------|-------------|
| **nuevo** | Usuario creado, pero sin validar correo. El nombre de usuario y el correo pueden modificarse. Sin acceso al sistema. |
| **activo** | Usuario con correo validado y acceso normal. El nombre de usuario se vuelve inalterable. No puede volver a estado Nuevo. |
| **pendiente_verificacion** | Estado temporal cuando el usuario solicita cambio de correo. Requiere ingresar su contraseña y verificar el nuevo correo. Sin acceso al sistema. |
| **suspendido** | Acceso bloqueado temporalmente por decisión de un usuario con jerarquía superior. No puede iniciar sesión hasta su reactivación. |
| **retirado** | Baja formal del usuario dentro del emisor (temporal o permanente). No tiene acceso. Solo puede reactivarse mediante nueva verificación de correo solicitada por el creador. |

## Matriz de Transiciones Permitidas

| Estado Inicial | Estados Permitidos | Descripción / Nota |
|----------------|-------------------|-------------------|
| **nuevo** | activo | Se realiza al validar correctamente el correo electrónico. |
| **activo** | suspendido | Bloqueo temporal por decisión administrativa. |
| **activo** | pendiente_verificacion | Cuando el usuario solicita el cambio de correo electrónico. |
| **activo** | retirado | Baja definitiva del usuario. |
| **pendiente_verificacion** | activo | Al completar correctamente la verificación del nuevo correo. |
| **pendiente_verificacion** | suspendido | Bloqueo temporal durante el proceso de verificación. |
| **suspendido** | activo | Reactivación de la cuenta por autorización administrativa. |
| **suspendido** | retirado | Baja definitiva mientras el usuario se encuentra suspendido. |
| **retirado** | pendiente_verificacion | Reactivación solicitada por el usuario creador. |

## Diagrama de Flujo

```
                    ┌─────────┐
                    │  NUEVO  │
                    └────┬────┘
                         │
                    Verificar correo
                         │
                         ▼
        ┌────────────────────────────────┐
        │          ACTIVO                │◄─────┐
        └──┬────────┬────────┬───────────┘      │
           │        │        │                   │
      Suspender  Cambiar   Retirar          Reactivar
                  correo                         │
           │        │        │                   │
           ▼        ▼        ▼                   │
      ┌─────────┐ ┌──────────────────┐    ┌─────────┐
      │SUSPENDIDO│ │PENDIENTE_VERIF.  │    │RETIRADO │
      └──┬───┬──┘ └────────┬─────────┘    └────┬────┘
         │   │             │                    │
    Reactivar│        Verificar            Solicitar
         │   │             │              reactivación
         └───┘             │                    │
                           ▼                    ▼
                      ┌─────────┐         ┌──────────────────┐
                      │ ACTIVO  │         │PENDIENTE_VERIF.  │
                      └─────────┘         └──────────────────┘
```

## Reglas Especiales

### Usuario Admin (admin@factura.local)
- **Siempre debe permanecer en estado "activo"**
- No puede cambiar a ningún otro estado
- Validación especial en el código

### Restricciones de Username
- **Estado "nuevo"**: Username puede ser modificado
- **Estado "activo" o superior**: Username es inalterable
- Esta restricción se aplica al verificar el correo electrónico

### Restricciones de Email
- Solo el propio usuario puede cambiar su email desde su cuenta
- Al cambiar el email, pasa a "pendiente_verificacion"
- Debe verificar el nuevo email para volver a "activo"

## Validaciones en el Código

### Modelo User (app/Models/User.php)

```php
// Obtener transiciones permitidas
User::getTransicionesPermitidas();

// Verificar si puede transicionar
$user->puedeTransicionarA('activo'); // true/false

// Obtener mensaje de error
$user->getMensajeTransicionInvalida('nuevo');
```

### Request Validation (UpdateUserRequest.php)

La validación de transiciones se realiza automáticamente al actualizar un usuario:

```php
'estado' => [
    'sometimes',
    'string',
    'in:nuevo,activo,pendiente_verificacion,suspendido,retirado',
    function ($attribute, $value, $fail) use ($userId) {
        $user = User::find($userId);
        if ($user && !$user->puedeTransicionarA($value)) {
            $fail($user->getMensajeTransicionInvalida($value));
        }
    }
],
```

## Ejemplos de Uso

### Transición Válida
```
Estado actual: nuevo
Estado destino: activo
✅ PERMITIDO - Usuario verificó su correo
```

### Transición Inválida
```
Estado actual: activo
Estado destino: nuevo
❌ RECHAZADO - Un usuario activo no puede volver a estado Nuevo
```

### Caso Especial - Reactivación
```
Estado actual: retirado
Estado destino: activo (directo)
❌ RECHAZADO

Flujo correcto:
1. retirado → pendiente_verificacion (solicitud de reactivación)
2. pendiente_verificacion → activo (verificación completada)
✅ PERMITIDO
```

## Mensajes de Error Personalizados

Cada transición inválida tiene un mensaje específico que explica por qué no está permitida y cuál es el flujo correcto.

Ejemplos:
- "Un usuario activo no puede volver al estado Nuevo"
- "No se puede activar directamente un usuario retirado. Primero debe pasar a Pendiente de verificación"
- "Un usuario suspendido solo puede pasar a: Activo o Retirado"

## Auditoría

Todos los cambios de estado quedan registrados en los logs del sistema con:
- Usuario que realizó el cambio
- Usuario afectado
- Estado anterior y nuevo
- Timestamp
- IP del usuario que realizó el cambio
