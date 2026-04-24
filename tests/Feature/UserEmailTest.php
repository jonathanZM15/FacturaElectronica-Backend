<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use App\Models\User;
use App\Mail\AccountConfirmationMail;
use App\Mail\PasswordSetupMail;
use App\Mail\AccountSuspendedMail;
use App\Mail\AccountReactivatedMail;
use App\Mail\AccountDeactivatedMail;
use App\Mail\AccountReactivationVerifyMail;
use App\Mail\EmailChangeNoticeMail;
use App\Mail\EmailChangeConfirmMail;
use App\Mail\LoginAttemptsAlertMail;

class UserEmailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Crear usuario nuevo envía AccountConfirmationMail
     */
    public function test_new_user_creation_sends_account_confirmation_email()
    {
        Mail::fake();

        // Admin user
        $admin = User::factory()->create([
            'role' => 'administrador',
            'estado' => 'activo',
            'email_verified_at' => now(),
        ]);

        // Create new user
        $response = $this->actingAs($admin)->postJson('/api/usuarios', [
            'cedula' => '1234567890',
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'username' => 'juanperez',
            'email' => 'juan@example.com',
            'role' => 'emisor',
            'estado' => 'nuevo',
        ]);

        $response->assertStatus(201);

        // Verify AccountConfirmationMail was sent
        Mail::assertSent(AccountConfirmationMail::class, function ($mail) {
            return $mail->hasTo('juan@example.com');
        });
    }

    /**
     * Test: No enviar correo a admin@factura.local
     */
    public function test_admin_account_no_email_sent()
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => 'administrador',
            'estado' => 'activo',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->postJson('/api/usuarios', [
            'cedula' => '0000000000',
            'nombres' => 'Admin',
            'apellidos' => 'Account',
            'username' => 'admin_account',
            'email' => 'admin@factura.local',
            'role' => 'administrador',
            'estado' => 'activo',
        ]);

        $response->assertStatus(201);

        // No mail should be sent to admin@factura.local
        Mail::assertNotSent(AccountConfirmationMail::class);
    }

    /**
     * Test: Verify email then send PasswordSetupMail
     */
    public function test_verify_email_then_password_setup_email()
    {
        Mail::fake();

        // Create user
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'estado' => 'nuevo',
        ]);

        // Create verification token
        $token = \Illuminate\Support\Str::random(60);
        DB::table('user_verification_tokens')->insert([
            'user_id' => $user->id,
            'token' => $token,
            'type' => 'email_verification',
            'metadata' => json_encode([
                'estado_anterior' => 'nuevo',
                'email_sent_to' => $user->email,
            ]),
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify email
        $response = $this->postJson('/api/verify-email', [
            'token' => $token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'Email verificado exitosamente. Revisa tu correo para establecer tu contraseña.']);

        // Verify PasswordSetupMail was sent
        Mail::assertSent(PasswordSetupMail::class, function ($mail) {
            return $mail->hasTo('test@example.com');
        });

        // Verify user is now activo
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'estado' => 'activo',
        ]);
        
        // Verify email_verified_at is set (check separately due to timestamp precision)
        $updatedUser = User::find($user->id);
        $this->assertNotNull($updatedUser->email_verified_at);
    }

    /**
     * Test: activo -> suspendido envía AccountSuspendedMail
     */
    public function test_user_suspension_sends_account_suspended_email()
    {
        Mail::fake();

        $user = User::factory()->create([
            'estado' => 'activo',
            'email_verified_at' => now(),
        ]);

        $admin = User::factory()->create([
            'role' => 'administrador',
            'estado' => 'activo',
            'email_verified_at' => now(),
        ]);

        // Update user estado to suspendido
        $this->actingAs($admin)->putJson("/api/usuarios/{$user->id}", [
            'estado' => 'suspendido',
        ]);

        // Verify AccountSuspendedMail was sent
        Mail::assertSent(AccountSuspendedMail::class);
    }

    /**
     * Test: suspendido -> activo envía AccountReactivatedMail
     */
    public function test_user_reactivation_sends_account_reactivated_email()
    {
        Mail::fake();

        $user = User::factory()->create([
            'estado' => 'suspendido',
            'email_verified_at' => now(),
        ]);

        $admin = User::factory()->create([
            'role' => 'administrador',
            'estado' => 'activo',
            'email_verified_at' => now(),
        ]);

        // Update user estado to activo
        $this->actingAs($admin)->putJson("/api/usuarios/{$user->id}", [
            'estado' => 'activo',
        ]);

        // Verify AccountReactivatedMail was sent
        Mail::assertSent(AccountReactivatedMail::class);
    }

    /**
     * Test: Estado -> retirado envía AccountDeactivatedMail
     */
    public function test_user_deactivation_sends_account_deactivated_email()
    {
        Mail::fake();

        $user = User::factory()->create([
            'estado' => 'activo',
            'email_verified_at' => now(),
        ]);

        $admin = User::factory()->create([
            'role' => 'administrador',
            'estado' => 'activo',
            'email_verified_at' => now(),
        ]);

        // Update user estado to retirado
        $this->actingAs($admin)->putJson("/api/usuarios/{$user->id}", [
            'estado' => 'retirado',
        ]);

        // Verify AccountDeactivatedMail was sent
        Mail::assertSent(AccountDeactivatedMail::class);
    }

    /**
     * Test: retirado -> pendiente_verificacion envía AccountReactivationVerifyMail con token real
     */
    public function test_user_reactivation_verification_with_real_token()
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => 'administrador',
            'estado' => 'activo',
            'email_verified_at' => now(),
        ]);

        $user = User::factory()->create([
            'estado' => 'retirado',
            'email' => 'retired@example.com',
            'email_verified_at' => now(),
            'created_by_id' => $admin->id,
        ]);

        // Reactivation from retirado must happen via resend-verification.
        $response = $this->actingAs($admin)->postJson("/api/usuarios/{$user->id}/resend-verification");
        $response->assertStatus(200);

        // Refresh user from DB
        $user->refresh();

        // Verify user estado changed
        $this->assertEquals('pendiente_verificacion', $user->estado);

        Mail::assertSent(AccountReactivationVerifyMail::class, function ($mail) {
            return $mail->hasTo('retired@example.com');
        });

        // Verify a real token was created (not token=pending)
        $token = DB::table('user_verification_tokens')
            ->where('user_id', $user->id)
            ->where('type', 'email_verification')
            ->where('used', false)
            ->first();

        $this->assertNotNull($token, 'No verification token was created');
        $this->assertNotEquals('pending', $token->token);
        $this->assertGreaterThan(10, strlen($token->token)); // Real token should be longer
    }

    /**
     * Test: Request email change envía dos correos
     */
    public function test_request_email_change_sends_two_emails()
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'estado' => 'activo',
            'email_verified_at' => now(),
        ]);

        // Request email change
        $response = $this->actingAs($user)->postJson("/api/usuarios/{$user->id}/request-email-change", [
            'new_email' => 'new@example.com',
        ]);

        $response->assertStatus(200);

        // Verify EmailChangeNoticeMail sent to old email
        Mail::assertSent(EmailChangeNoticeMail::class, function ($mail) {
            return $mail->hasTo('old@example.com');
        });

        // Verify EmailChangeConfirmMail sent to new email
        Mail::assertSent(EmailChangeConfirmMail::class, function ($mail) {
            return $mail->hasTo('new@example.com');
        });

        // Verify user is pendiente_verificacion
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'estado' => 'pendiente_verificacion',
        ]);
    }

    /**
     * Test: Confirm email change actualiza el correo
     */
    public function test_confirm_email_change_updates_email()
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'estado' => 'activo',
            'email_verified_at' => now(),
        ]);

        // Create email change token
        $token = \Illuminate\Support\Str::random(60);
        DB::table('user_verification_tokens')->insert([
            'user_id' => $user->id,
            'token' => $token,
            'type' => 'email_change_confirmation',
            'metadata' => json_encode([
                'old_email' => 'old@example.com',
                'new_email' => 'new@example.com',
                'requested_by_id' => $user->id,
                'requested_at' => now()->toDateTimeString(),
            ]),
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Confirm email change
        $response = $this->postJson('/api/confirm-email-change', [
            'token' => $token,
        ]);

        $response->assertStatus(200);

        // Verify email was updated
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new@example.com',
            'estado' => 'activo',
        ]);
    }

    /**
     * Test: 5 intentos fallidos dispara SendSuspiciousLoginEmailJob
     */
    public function test_five_failed_login_attempts_trigger_alert_email()
    {
        Mail::fake();
        Queue::fake();

        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('correct-password'),
            'estado' => 'activo',
            'email_verified_at' => now(),
        ]);

        // First 4 failed login attempts should return 401
        for ($i = 0; $i < 4; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => 'login@example.com',
                'password' => 'wrong-password',
            ]);
            $response->assertStatus(401);
        }

        // 5th failed attempt should return 403 (FORBIDDEN) and trigger account lock
        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ]);
        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'Demasiados intentos fallidos. Tu cuenta ha sido bloqueada por 10 minutos. Se ha enviado una notificación a tu correo.']);

        // Verify user is locked
        $user->refresh();
        $this->assertNotNull($user->locked_until);

        // Verify SendSuspiciousLoginEmailJob was dispatched
        Queue::assertPushed(\App\Jobs\SendSuspiciousLoginEmailJob::class);
    }

    /**
     * Test: Exclude admin@factura.local from state transitions
     */
    public function test_admin_account_cannot_change_state()
    {
        $admin = User::where('email', 'admin@factura.local')->first();
        if (!$admin) {
            $admin = User::factory()->create([
                'email' => 'admin@factura.local',
                'role' => 'administrador',
                'estado' => 'activo',
                'email_verified_at' => now(),
            ]);
        }

        $superAdmin = User::factory()->create([
            'role' => 'administrador',
            'estado' => 'activo',
            'email_verified_at' => now(),
        ]);

        // Try to change admin state to suspendido
        $response = $this->actingAs($superAdmin)->putJson("/api/usuarios/{$admin->id}", [
            'estado' => 'suspendido',
        ]);

        // The request is rejected at validation level before hitting the controller guard.
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Un usuario activo solo puede pasar a: Suspendido, Pendiente de verificación o Retirado'
        ]);

        // Verify state unchanged
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'estado' => 'activo',
        ]);
    }
    /**
     * Test: Cambio de email
     */
    /**
     * Test: Cambio de email devuelve respuesta correcta
     */
    public function test_email_change_request_response()
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'estado' => 'activo',
            'email_verified_at' => now(),
        ]);

        // Request email change
        $response = $this->actingAs($user)->postJson("/api/usuarios/{$user->id}/request-email-change", [
            'new_email' => 'new@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'message' => 'Solicitud de cambio de correo enviada. Revisa ambos correos para confirmar el cambio.'
        ]);

        // Verify emails were sent (notice to old email + confirm link to new email)
        Mail::assertSent(EmailChangeNoticeMail::class);
        Mail::assertSent(EmailChangeConfirmMail::class);

        // Verify token was created
        $token = DB::table('user_verification_tokens')
            ->where('user_id', $user->id)
            ->where('type', 'email_change_confirmation')
            ->where('used', false)
            ->first();

        $this->assertNotNull($token);
    }
}
