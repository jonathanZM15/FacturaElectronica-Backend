<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log; // Importar Facade de Log
use Illuminate\Support\Facades\DB;

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

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'Email no registrado'], Response::HTTP_NOT_FOUND);
        }

        // Aquí normalmente dispararías un email con el link o token.
        // Para este entorno de desarrollo simulamos el envío.
        // Puedes integrar Mailables si lo deseas.

        return response()->json(['message' => 'Instrucciones enviadas al correo si existe la cuenta']);
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

        // Actualizar la contraseña
        $user->password = Hash::make($data['password']);
        $user->save();

        Log::info('Password Changed', ['user_id' => $user->id]);

        return response()->json(['message' => 'Contraseña actualizada correctamente']);
    }
}
