<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LoginAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\PasswordRecoveryMail;
use App\Mail\SuspiciousLoginMail;
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
            'password' => ['required', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $token = $user->createToken('default')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token], Response::HTTP_CREATED);
    }

    public function login(Request $request)
    {
        $data = $request->only(['email', 'password']);

        $validator = Validator::make($data, [
            'email' => ['required','string', 'max:255'],
            'password' => ['required','string'],
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

        // Verificar si la cuenta está bloqueada
        if ($user->locked_until && now()->lt($user->locked_until)) {
            $minutesRemaining = now()->diffInMinutes($user->locked_until);
            
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
                'message' => "Cuenta bloqueada. Intenta de nuevo en {$minutesRemaining} minutos."
            ], Response::HTTP_FORBIDDEN);
        }

        // Desbloquear automáticamente si el tiempo ya pasó
        if ($user->locked_until && now()->gte($user->locked_until)) {
            $user->locked_until = null;
            $user->failed_login_attempts = 0;
            $user->save();
            Log::info('Account Auto-Unlocked', ['user_id' => $user->id]);
        }

        // Validar estado del usuario (solo "activo" puede hacer login)
        if ($user->estado !== 'activo') {
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

            $mensaje = $mensajes[$user->estado] ?? 'Tu cuenta no está activa.';

            Log::warning('Login Denied - Account Not Active', [
                'user_id' => $user->id,
                'estado' => $user->estado,
                'ip' => $ipAddress
            ]);

            return response()->json(['message' => $mensaje], Response::HTTP_FORBIDDEN);
        }

        // Verificar contraseña
        if (!Hash::check($password, $user->password)) {
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

                // Enviar email de alerta
                try {
                    Mail::to($user->email)->send(new SuspiciousLoginMail(
                        $user,
                        $ipAddress,
                        $user->failed_login_attempts,
                        $deviceInfo,
                        now()->format('d/m/Y H:i:s')
                    ));
                    Log::info('Suspicious login email sent', ['user_id' => $user->id]);
                } catch (\Exception $e) {
                    Log::error('Failed to send suspicious login email', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }

                return response()->json([
                    'message' => 'Demasiados intentos fallidos. Tu cuenta ha sido bloqueada por 10 minutos. Se ha enviado una notificación a tu correo.'
                ], Response::HTTP_FORBIDDEN);
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

            // Enviar email de alerta si ya hay 5 intentos (antes de bloquear)
            if ($user->failed_login_attempts === 5) {
                try {
                    Mail::to($user->email)->send(new SuspiciousLoginMail(
                        $user,
                        $ipAddress,
                        $user->failed_login_attempts,
                        $deviceInfo,
                        now()->format('d/m/Y H:i:s')
                    ));
                    Log::info('Warning email sent after 5 failed attempts', ['user_id' => $user->id]);
                } catch (\Exception $e) {
                    Log::error('Failed to send warning email', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $intentosRestantes = 5 - $user->failed_login_attempts;

            Log::error('Authentication Failed: Invalid password', [
                'user_id' => $user->id,
                'identifier' => $identifier,
                'attempts' => $user->failed_login_attempts,
                'ip' => $ipAddress
            ]);

            return response()->json([
                'message' => $intentosRestantes > 0 
                    ? "Credenciales inválidas. Te quedan {$intentosRestantes} intentos antes del bloqueo"
                    : 'Credenciales inválidas',
                'intentos_restantes' => $intentosRestantes > 0 ? $intentosRestantes : 0
            ], Response::HTTP_UNAUTHORIZED);
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

        // Revoke previous tokens (optional)
        $user->tokens()->delete();

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
            // Revoke the token used for this session and log counts for debugging
            try {
                $patModel = '\\Laravel\\Sanctum\\PersonalAccessToken';
                $before = $patModel::where('tokenable_id', $user->id)->count();
                Log::info('Logout: tokens_before', ['user_id' => $user->id, 'count' => $before]);

                if ($request->user()?->currentAccessToken()) {
                    $request->user()->currentAccessToken()->delete();
                } else {
                    $user->tokens()->delete();
                }

                $after = $patModel::where('tokenable_id', $user->id)->count();
                Log::info('Logout: tokens_after', ['user_id' => $user->id, 'count' => $after]);
            } catch (\Throwable $e) {
                Log::error('Logout error', ['err' => $e->getMessage()]);
                // Fallback: delete all tokens to ensure logout
                $user->tokens()->delete();
            }
        }
        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function me(Request $request)
    {
        try {
            Log::info('Me endpoint', ['bearer' => $request->bearerToken(), 'user_id' => $request->user()?->id]);
        } catch (\Throwable $e) {
            Log::info('Me endpoint error', ['err' => $e->getMessage()]);
        }

        return response()->json($request->user());
    }

    public function passwordRecovery(Request $request)
    {
        $data = $request->only(['email']);

        $validator = Validator::make($data, [
            'email' => ['required', 'email']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Normalizar email a minúsculas para búsqueda case-insensitive
        $email = strtolower(trim($data['email']));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            return response()->json(['message' => 'Email no registrado'], Response::HTTP_NOT_FOUND);
        }

        // Verificar si existe un token reciente (throttling manual más informativo)
        $recentToken = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->first();

        if ($recentToken) {
            $createdAt = \Carbon\Carbon::parse($recentToken->created_at);
            $throttleSeconds = config('auth.passwords.users.throttle', 60);
            $secondsRemaining = $throttleSeconds - $createdAt->diffInSeconds(now());
            
            if ($secondsRemaining > 0) {
                Log::info('Password recovery throttled', [
                    'user_id' => $user->id,
                    'seconds_remaining' => $secondsRemaining
                ]);
                return response()->json([
                    'message' => "Por favor espera {$secondsRemaining} segundos antes de solicitar otro enlace de recuperación"
                ], Response::HTTP_TOO_MANY_REQUESTS);
            }
        }

        // Generar token de restablecimiento usando el broker de password
        $token = Password::broker()->createToken($user);

        // Construir URL al frontend donde el usuario cambiará su contraseña
        $frontend = env('FRONTEND_URL', config('app.url'));
        $url = rtrim($frontend, '/') . '/cambiarPassword?token=' . urlencode($token) . '&email=' . urlencode($user->email);

        try {
            Mail::to($user->email)->send(new PasswordRecoveryMail($url, $user));
            Log::info('Password recovery email sent', ['user_id' => $user->id, 'email' => $user->email]);
        } catch (\Throwable $e) {
            Log::error('Error sending password recovery email', ['err' => $e->getMessage()]);
            return response()->json(['message' => 'Error al enviar el correo de recuperación'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json(['message' => 'Instrucciones enviadas al correo si existe la cuenta']);
    }

    /**
     * Completa el restablecimiento de contraseña usando token + email + password
     */
    public function resetPassword(Request $request)
    {
        $data = $request->only(['email', 'token', 'password', 'password_confirmation']);

        $validator = Validator::make($data, [
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $status = Password::broker()->reset(
            $data,
            function ($user, $password) {
                // El modelo User tiene el cast 'password' => 'hashed'
                // por lo que asignamos la contraseña en texto y el cast se encargará de hashearla.
                $user->password = $password;
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
            'password' => ['required', 'string', 'min:6'],
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
