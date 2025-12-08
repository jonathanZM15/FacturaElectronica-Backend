<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuración de Sesiones Múltiples
    |--------------------------------------------------------------------------
    |
    | Estas opciones controlan cómo se manejan las sesiones simultáneas
    | de usuarios en diferentes dispositivos.
    |
    */

    // Permitir múltiples sesiones simultáneas
    'allow_multiple_sessions' => env('ALLOW_MULTIPLE_SESSIONS', true),

    // Límite máximo de dispositivos simultáneos (null = ilimitado)
    'max_devices' => env('MAX_DEVICES_PER_USER', null),

    // Revocar sesiones antiguas cuando se excede el límite
    'revoke_oldest_on_limit' => env('REVOKE_OLDEST_ON_LIMIT', true),

    // Tiempo de expiración de tokens en minutos (null = sin expiración)
    'token_expiration_minutes' => env('TOKEN_EXPIRATION_MINUTES', null),

    // Revocar todos los tokens al cambiar contraseña
    'revoke_on_password_change' => env('REVOKE_ON_PASSWORD_CHANGE', true),

    // Notificar al usuario sobre nuevos inicios de sesión
    'notify_new_login' => env('NOTIFY_NEW_LOGIN', false),
];
