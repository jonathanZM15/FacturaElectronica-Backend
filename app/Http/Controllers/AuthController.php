<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LoginAttempt;
use App\Jobs\SendSuspiciousLoginEmailJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Jenssegers\Agent\Agent;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->only(['name', 'email', 'password']);

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'max:72', new \App\Rules\PasswordStorageLimit(), \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = User::create([
            'nombres' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => \App\Enums\UserRole::CAJERO,
            'estado' => 'nuevo',
        ]);

        $token = $user->createToken('default')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token], Response::HTTP_CREATED);
    }

    public function login(Request $request)
    {
        $data = $request->only(['email', 'password']);

        $validator = Validator::make($data, [
            'email' => ['required','string', 'max:255'],
            'password' => ['required','string', 'max:1024'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $identifier = $data['email']; 
        $password = $data['password'];
        $ipAddress = $request->ip();
        $userAgent = $request->header('User-Agent');
        
        // Parsear información del dispositivo
        $agent = new Agent();
        $agent->setUserAgent($userAgent);
        $deviceType = $agent->isMobile() ? 'mobile' : ($agent->isTablet() ? 'tablet' : 'desktop');
        $browser = $agent->browser();
        $platform = $agent->platform();
        $deviceInfo = "{$browser} en {$platform} ({$deviceType})";
        
        Log::info('Login Attempt', ['identifier' => $identifier, 'ip' => $ipAddress]);
        
        // Buscar usuario por email O username
        $user = User::where(function ($query) use ($identifier) {
            $query->where('email', $identifier)
                  ->orWhere('username', $identifier);
        })->first();

        // Registrar intento fallido si no se encuentra el usuario
        if (!$user) {
            LoginAttempt::create([
                'user_id' => null,
                'identifier' => $identifier,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'device_type' => $deviceType,
                'browser' => $browser,
                'platform' => $platform,
                'success' => false,
                'failure_reason' => 'Usuario no encontrado',
                'attempted_at' => now(),
            ]);

            Log::warning('User Not Found', ['identifier' => $identifier, 'ip' => $ipAddress]);
            return response()->json(['message' => 'Credenciales inválidas'], Response::HTTP_UNAUTHORIZED);
        }

        $passwordMatches = Hash::check($password, $user->password);

        // Verificar si la cuenta está bloqueada
        if ($user->locked_until && now()->lt($user->locked_until)) {
            // Calcular minutos restantes correctamente
            $lockedUntil = \Carbon\Carbon::parse($user->locked_until);
            $secondsRemaining = now()->diffInSeconds($lockedUntil, false);
            $minutesRemaining = (int) ceil($secondsRemaining / 60);
            
            // Asegurar que esté entre 1 y 10 minutos
            if ($minutesRemaining < 1) $minutesRemaining = 1;
            if ($minutesRemaining > 10) $minutesRemaining = 10;
            
            LoginAttempt::create([
                'user_id' => $user->id,
                'identifier' => $identifier,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'device_type' => $deviceType,
                'browser' => $browser,
                'platform' => $platform,
                'success' => false,
                'failure_reason' => 'Cuenta bloqueada',
                'attempted_at' => now(),
            ]);

            Log::warning('Account Locked', [
                'user_id' => $user->id,
                'locked_until' => $user->locked_until,
                'ip' => $ipAddress
            ]);

            return response()->json([
                'message' => $passwordMatches
                    ? "Cuenta bloqueada. Intenta de nuevo en {$minutesRemaining} minutos."
                    : 'Credenciales inválidas'
            ], $passwordMatches ? Response::HTTP_FORBIDDEN : Response::HTTP_UNAUTHORIZED);
        }

        // Desbloquear automáticamente si el tiempo ya pasó
        if ($user->locked_until && now()->gte($user->locked_until)) {
            $user->locked_until = null;
            $user->failed_login_attempts = 0;
            $user->save();
            Log::info('Account Auto-Unlocked', ['user_id' => $user->id]);
        }

        // Verificar contraseña
        if (!$passwordMatches) {
            // Incrementar intentos fallidos
            $user->failed_login_attempts = ($user->failed_login_attempts ?? 0) + 1;
            
            // Bloquear cuenta después de 5 intentos
            if ($user->failed_login_attempts >= 5) {
                $user->locked_until = now()->addMinutes(10);
                $user->save();

                LoginAttempt::create([
                    'user_id' => $user->id,
                    'identifier' => $identifier,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'device_type' => $deviceType,
                    'browser' => $browser,
                    'platform' => $platform,
                    'success' => false,
                    'failure_reason' => 'Contraseña incorrecta - Cuenta bloqueada',
                    'attempted_at' => now(),
                ]);

                Log::error('Account Locked After 5 Failed Attempts', [
                    'user_id' => $user->id,
                    'ip' => $ipAddress
                ]);

                // Enviar email de alerta (asincrónico vía Job)
                dispatch(new SendSuspiciousLoginEmailJob(
                    $user,
                    $deviceType,
                    $browser,
                    $platform,
                    $ipAddress
                ));

                Log::info('Suspicious login email job dispatched', ['user_id' => $user->id]);

                return response()->json([
                    'message' => 'Credenciales inválidas'
                ], Response::HTTP_UNAUTHORIZED);
            }

            $user->save();

            LoginAttempt::create([
                'user_id' => $user->id,
                'identifier' => $identifier,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'device_type' => $deviceType,
                'browser' => $browser,
                'platform' => $platform,
                'success' => false,
                'failure_reason' => 'Contraseña incorrecta',
                'attempted_at' => now(),
            ]);


            Log::error('Authentication Failed: Invalid password', [
                'user_id' => $user->id,
                'identifier' => $identifier,
                'attempts' => $user->failed_login_attempts,
                'ip' => $ipAddress
            ]);

            return response()->json([
                'message' => 'Credenciales inválidas'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Validar estado del usuario (solo "activo" puede hacer login)
        if ($user->estado !== 'activo' || $user->password_setup_required) {
            LoginAttempt::create([
                'user_id' => $user->id,
                'identifier' => $identifier,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'device_type' => $deviceType,
                'browser' => $browser,
                'platform' => $platform,
                'success' => false,
                'failure_reason' => "Estado: {$user->estado}",
                'attempted_at' => now(),
            ]);

            $mensajes = [
                'nuevo' => 'Debes verificar tu correo electrónico antes de iniciar sesión.',
                'pendiente_verificacion' => 'Tu cuenta está pendiente de verificación.',
                'suspendido' => 'Tu cuenta ha sido suspendida. Contacta al administrador.',
                'retirado' => 'Tu cuenta ha sido dada de baja.',
            ];

            $mensaje = $user->password_setup_required ? 'Establece tu contraseña desde el enlace recibido por correo antes de iniciar sesión.' : ($mensajes[$user->estado] ?? 'Tu cuenta no está activa.');

            Log::warning('Login Denied - Account Not Active', [
                'user_id' => $user->id,
                'estado' => $user->estado,
                'ip' => $ipAddress
            ]);

            return response()->json(['message' => $mensaje], Response::HTTP_FORBIDDEN);
        }

        // Login exitoso - Resetear intentos fallidos
        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->last_login_at = now();
        $user->last_login_ip = $ipAddress;
        $user->last_user_agent = $userAgent;
        $user->save();

        // Registrar intento exitoso
        LoginAttempt::create([
            'user_id' => $user->id,
            'identifier' => $identifier,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'device_type' => $deviceType,
            'browser' => $browser,
            'platform' => $platform,
            'success' => true,
            'failure_reason' => null,
            'attempted_at' => now(),
        ]);

        // ⚠️ NO revocar tokens anteriores - permitir múltiples sesiones simultáneas
        // $user->tokens()->delete();

        $token = $user->createToken('default')->plainTextToken;
        
        Log::info('Login Success', [
            'user_id' => $user->id,
            'ip' => $ipAddress,
            'device' => $deviceInfo
        ]);

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            try {
                // Eliminar solo el token actual (1 query)
                $request->user()->currentAccessToken()?->delete();
            } catch (\Throwable $e) {
                Log::error('Logout error', ['err' => $e->getMessage()]);
            }
        }
        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function me(Request $request)
    {
        try {
            Log::info('Me endpoint', ['user_id' => $request->user()?->id]);
        } catch (\Throwable $e) {
            Log::info('Me endpoint error', ['err' => $e->getMessage()]);
        }

        return response()->json($request->user());
    }

    public function passwordRecovery(Request $request)
    {
        $data = $request->only(['email']);

        $validator = Validator::make($data, [
            'email' => ['required', 'email', 'max:255']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return (new \Illuminate\Support\Timebox())->call(function () use ($data) {
            $email = mb_strtolower(trim($data['email']));
            $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
            if ($user) {
                try {
                    DB::transaction(function () use ($user) {
                        // Serializa solicitudes simultáneas de la misma cuenta.
                        $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                        $broker = Password::broker();
                        if (!$broker->getRepository()->recentlyCreatedToken($user)) {
                            $token = $broker->createToken($user);
                            \App\Jobs\SendPasswordRecoveryMailJob::dispatch($user->id, $user->email, $token);
                        }
                    }, 3);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
            return response()->json(['message' => 'Instrucciones enviadas al correo si existe la cuenta']);
        }, 200000);
    }

    /**
     * Completa el restablecimiento de contraseña usando token + email + password
     */
    public function resetPassword(Request $request)
    {
        $data = $request->only(['email', 'token', 'password', 'password_confirmation']);

        $validator = Validator::make($data, [
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:72', new \App\Rules\PasswordStorageLimit(), \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $status = DB::transaction(function () use ($data) {
            User::where('email', $data['email'])->lockForUpdate()->first();
            return Password::broker()->reset(
                $data,
                function ($user, $password) {
                    // El modelo User tiene el cast 'password' => 'hashed'
                    // por lo que asignamos la contraseña en texto y el cast se encargará de hashearla.
                    $user->password = $password;
                    $user->password_setup_required = false;
                    $user->setRememberToken(Str::random(60));
                    $user->save();
                    event(new PasswordReset($user));
                
                    // Revoke existing personal access tokens
                    try {
                        $user->tokens()->delete();
                        Log::info('Tokens revoked after password reset', ['user_id' => $user->id]);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to revoke tokens after password reset', ['err' => $e->getMessage()]);
                    }
                
                    // Log successful password reset
                    Log::info('Password reset successful', [
                        'user_id' => $user->id,
                        'email' => $user->email
                    ]);
                }
            );
        }, 3);

        if ($status == Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Contraseña restablecida correctamente']);
        }

        // Log failed password reset attempt
        Log::warning('Password reset failed', [
            'email' => $data['email'],
            'status' => $status
        ]);

        return response()->json(['message' => 'Token inválido o expirado'], Response::HTTP_BAD_REQUEST);
    }

    public function cambiarPassword(Request $request)
    {
        $data = $request->only(['password', 'current_password']);

        $validator = Validator::make($data, [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'max:72', new \App\Rules\PasswordStorageLimit(), \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $request->user();

        // Verificar que la contraseña actual sea correcta
        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'La contraseña actual es incorrecta'], Response::HTTP_UNAUTHORIZED);
        }

    // Actualizar la contraseña: asignamos texto plano y el cast 'hashed' del modelo
    // realizará el hash al persistir.
    $user->password = $data['password'];
    $user->save();

        Log::info('Password Changed', ['user_id' => $user->id]);

        return response()->json(['message' => 'Contraseña actualizada correctamente']);
    }
}
