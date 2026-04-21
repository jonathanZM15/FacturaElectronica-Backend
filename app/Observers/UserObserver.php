<?php

namespace App\Observers;

use App\Models\User;
use App\Mail\{
    AccountSuspendedMail,
    AccountReactivatedMail,
    AccountDeactivatedMail,
    AccountReactivationVerifyMail,
    EmailChangeConfirmMail
};
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    /**
     * Handle the User "updated" event.
     * 
     * Se dispara después de actualizar un usuario.
     * Envía correos automáticamente cuando cambia el estado o email.
     */
    public function updated(User $user)
    {
        // Obtener los valores originales antes del cambio
        $original = $user->getOriginal();
        $changes = $user->getChanges();

        // Si cambió el estado, enviar correo correspondiente
        if (isset($changes['estado']) && $changes['estado'] !== $original['estado'] ?? null) {
            $estadoAnterior = $original['estado'] ?? null;
            $estadoNuevo = $changes['estado'];

            Log::info('Usuario cambió de estado', [
                'usuario_id' => $user->id,
                'email' => $user->email,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $estadoNuevo,
            ]);

            // No enviar correos al usuario admin@factura.local
            if ($user->email === 'admin@factura.local') {
                return;
            }

            try {
                match (true) {
                    // activo → suspendido: Enviar notificación de suspensión
                    $estadoAnterior === 'activo' && $estadoNuevo === 'suspendido' => 
                        Mail::to($user->email)->send(new AccountSuspendedMail($user)),

                    // suspendido → activo: Enviar notificación de reactivación
                    $estadoAnterior === 'suspendido' && $estadoNuevo === 'activo' => 
                        Mail::to($user->email)->send(new AccountReactivatedMail($user)),

                    // Cualquier estado → retirado: Enviar notificación de desactivación
                    in_array($estadoAnterior, ['activo', 'suspendido', 'pendiente_verificacion', 'nuevo'], true) && $estadoNuevo === 'retirado' => 
                        Mail::to($user->email)->send(new AccountDeactivatedMail($user)),

                    // retirado → pendiente_verificacion: Enviar solicitud de verificación para reactivación
                    $estadoAnterior === 'retirado' && $estadoNuevo === 'pendiente_verificacion' => 
                        Mail::to($user->email)->send(new AccountReactivationVerifyMail(
                            $user,
                            config('app.frontend_url', 'http://localhost:3000') . '/verify-email?token=pending'
                        )),

                    default => null
                };

                Log::info('Correo de cambio de estado enviado', [
                    'usuario_id' => $user->id,
                    'email' => $user->email,
                    'transicion' => "$estadoAnterior → $estadoNuevo"
                ]);
            } catch (\Exception $e) {
                Log::error('Error enviando correo de cambio de estado', [
                    'usuario_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                    'transicion' => "$estadoAnterior → $estadoNuevo"
                ]);
                // No detener la operación si falla el email
            }
        }

        // Si cambió el email y pasó a pendiente_verificacion, enviar confirmación
        if (isset($changes['email']) && isset($changes['estado']) && $changes['estado'] === 'pendiente_verificacion') {
            try {
                // El correo de verificación ya se envía en UserController::sendVerificationEmail()
                // Este observer solo registra el evento
                Log::info('Usuario en pendiente_verificacion tras cambio de email', [
                    'usuario_id' => $user->id,
                    'email_anterior' => $original['email'] ?? null,
                    'email_nuevo' => $changes['email'],
                ]);
            } catch (\Exception $e) {
                Log::error('Error procesando cambio de email', [
                    'usuario_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
