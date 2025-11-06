<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log; // Importar Facade de Log
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\PasswordRecoveryMail;
use Illuminate\Auth\Events\PasswordReset;

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
        
        Log::info('Login Attempt', ['identifier' => $identifier]);
        
        
        $user = User::where(function ($query) use ($identifier) {
            // Opción 1: Email (búsqueda normal)
            $query->where('email', $identifier);
  
            $query->orWhere(DB::raw('LOWER(name)'), '=', strtolower($identifier));
        })->first();


        if ($user) {
            Log::info('User Found', ['user_id' => $user->id, 'email' => $user->email, 'name' => $user->name]);
        } else {
            Log::warning('User Not Found', ['identifier' => $identifier]);
        }

        if (! $user) {
             Log::error('Authentication Failed: User not found.', ['identifier' => $identifier]);
             return response()->json(['message' => 'Credenciales invalidas'], Response::HTTP_UNAUTHORIZED);
        }
        
        if (! Hash::check($password, $user->password)) {
            Log::error('Authentication Failed: Invalid password.', ['user_id' => $user->id, 'identifier' => $identifier]);
            return response()->json(['message' => 'Credenciales invalidas'], Response::HTTP_UNAUTHORIZED);
        }

        // Revoke previous tokens (optional)
        $user->tokens()->delete();

        $token = $user->createToken('default')->plainTextToken;
        
        Log::info('Login Success', ['user_id' => $user->id]);

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
