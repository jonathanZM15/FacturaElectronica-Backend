<?php

namespace App\Services;

use App\Models\User;
use App\Mail\{
    AccountConfirmationMail,
    PasswordSetupMail,
    AccountReactivationVerifyMail,
    EmailChangeNoticeMail,
    EmailChangeConfirmMail
};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserEmailService
{
    /**
     * Invalidar tokens anteriores no usados para un usuario y tipo específico
     */
    public function invalidatePreviousTokens(int $userId, string $tokenType): void
    {
        DB::table('user_verification_tokens')
            ->where('user_id', $userId)
            ->where('type', $tokenType)
            ->where('used', false)
            ->update([
                'used' => true,
                'used_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Crear un token de verificación con metadata
     */
    public function createToken(int $userId, string $tokenType, array $metadata = [], int $expirationHours = 24): string
    {
        $token = Str::random(60);

        DB::table('user_verification_tokens')->insert([
            'user_id' => $userId,
            'token' => $token,
            'type' => $tokenType,
            'metadata' => json_encode($metadata),
            'expires_at' => now()->addHours($expirationHours),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return $token;
    }

    /**
     * Construir URL del frontend para verificación de email
     */
    public function buildVerificationUrl(string $token): string
    {
        return config('app.frontend_url', 'http://localhost:3000') . '/verify-email?token=' . $token;
    }

    /**
     * Construir URL del frontend para cambio de contraseña
     */
    public function buildPasswordSetupUrl(string $token): string
    {
        return config('app.frontend_url', 'http://localhost:3000') . '/change-password?token=' . $token;
    }

    /**
     * Construir URL del frontend para confirmación de cambio de correo
     */
    public function buildEmailChangeConfirmUrl(string $token): string
    {
        return config('app.frontend_url', 'http://localhost:3000') . '/confirm-email-change?token=' . $token;
    }

    /**
     * Enviar correo de verificación según el estado anterior del usuario
     * - Usuario nuevo: AccountConfirmationMail
     * - Usuario suspendido o retirado: AccountReactivationVerifyMail
     */
    public function sendVerificationEmailByState(User $user, ?string $estadoAnterior = null): void
    {
        if ($estadoAnterior === 'suspendido' || $estadoAnterior === 'retirado') {
            $this->sendAccountReactivationVerifyMail($user, $estadoAnterior);
        } else {
            $this->sendAccountConfirmationMail($user, $estadoAnterior);
        }
    }

    /**
     * Enviar correo de confirmación de cuenta para usuario nuevo
     */
    public function sendAccountConfirmationMail(User $user, ?string $estadoAnterior = null): void
    {
        // Invalidar tokens previos
        $this->invalidatePreviousTokens($user->id, 'email_verification');

        // Crear token con metadata
        $metadata = [
            'estado_anterior' => $estadoAnterior ?? $user->estado,
            'email_sent_to' => $user->email,
            'purpose' => 'new_account_verification'
        ];

        $token = $this->createToken($user->id, 'email_verification', $metadata, 24);
        $url = $this->buildVerificationUrl($token);

        try {
            Mail::to($user->email)->send(new AccountConfirmationMail($user, $url));
            Log::info('Correo de confirmación de cuenta enviado', [
                'usuario_id' => $user->id,
                'email' => $user->email
            ]);
        } catch (\Exception $e) {
            Log::error('Error enviando correo de confirmación de cuenta', [
                'usuario_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Enviar correo de setup de contraseña (después de verificar email)
     */
    public function sendPasswordSetupMail(User $user): void
    {
        // Invalidar tokens previos de este tipo
        $this->invalidatePreviousTokens($user->id, 'password_setup');

        // Crear token con metadata
        $metadata = [
            'purpose' => 'password_setup'
        ];

        $token = $this->createToken($user->id, 'password_setup', $metadata, 48);
        $url = $this->buildPasswordSetupUrl($token);

        try {
            Mail::to($user->email)->send(new PasswordSetupMail($user, $url));
            Log::info('Correo de setup de contraseña enviado', [
                'usuario_id' => $user->id,
                'email' => $user->email
            ]);
        } catch (\Exception $e) {
            Log::error('Error enviando correo de setup de contraseña', [
                'usuario_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Enviar correo de reactivación con verificación (retirado -> pendiente_verificacion)
     */
    public function sendAccountReactivationVerifyMail(User $user, string $estadoAnterior = 'retirado'): void
    {
        // Invalidar tokens previos
        $this->invalidatePreviousTokens($user->id, 'email_verification');

        // Crear token con metadata
        $metadata = [
            'estado_anterior' => $estadoAnterior,
            'email_sent_to' => $user->email,
            'purpose' => 'account_reactivation'
        ];

        $token = $this->createToken($user->id, 'email_verification', $metadata, 48);
        $url = $this->buildVerificationUrl($token);

        try {
            Mail::to($user->email)->send(new AccountReactivationVerifyMail($user, $url));
            Log::info('Correo de verificación de reactivación enviado', [
                'usuario_id' => $user->id,
                'email' => $user->email
            ]);
        } catch (\Exception $e) {
            Log::error('Error enviando correo de verificación de reactivación', [
                'usuario_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Solicitar cambio de correo: enviar notificación al correo anterior y confirmación al nuevo
     */
    public function requestEmailChange(User $user, string $newEmail): void
    {
        $oldEmail = $user->email;

        // Invalidar tokens previos de cambio de correo
        $this->invalidatePreviousTokens($user->id, 'email_change_confirmation');

        // Crear token para confirmación
        $metadata = [
            'old_email' => $oldEmail,
            'new_email' => $newEmail,
            'requested_by_id' => auth()->id(),
            'requested_at' => now()->toDateTimeString(),
            'purpose' => 'email_change'
        ];

        $token = $this->createToken($user->id, 'email_change_confirmation', $metadata, 48);
        $confirmUrl = $this->buildEmailChangeConfirmUrl($token);

        try {
            // Enviar notificación al correo anterior
            Mail::to($oldEmail)->send(new EmailChangeNoticeMail($user, $newEmail));
            Log::info('Correo de notificación de cambio enviado al correo anterior', [
                'usuario_id' => $user->id,
                'old_email' => $oldEmail
            ]);

            // Enviar confirmación al nuevo correo
            Mail::to($newEmail)->send(new EmailChangeConfirmMail($user, $confirmUrl));
            Log::info('Correo de confirmación de cambio enviado al nuevo correo', [
                'usuario_id' => $user->id,
                'new_email' => $newEmail
            ]);
        } catch (\Exception $e) {
            Log::error('Error enviando correos de cambio de correo', [
                'usuario_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
