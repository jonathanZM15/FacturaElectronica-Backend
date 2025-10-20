<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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
            'email' => ['required','email'],
            'password' => ['required','string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas'], Response::HTTP_UNAUTHORIZED);
        }

        // Revoke previous tokens (optional)
        $user->tokens()->delete();

        $token = $user->createToken('default')->plainTextToken;

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
                \Log::info('Logout: tokens_before', ['user_id' => $user->id, 'count' => $before]);

                if ($request->user()?->currentAccessToken()) {
                    $request->user()->currentAccessToken()->delete();
                } else {
                    $user->tokens()->delete();
                }

                $after = $patModel::where('tokenable_id', $user->id)->count();
                \Log::info('Logout: tokens_after', ['user_id' => $user->id, 'count' => $after]);
            } catch (\Throwable $e) {
                \Log::error('Logout error', ['err' => $e->getMessage()]);
                // Fallback: delete all tokens to ensure logout
                $user->tokens()->delete();
            }
        }
        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function me(Request $request)
    {
        try {
            \Log::info('Me endpoint', ['bearer' => $request->bearerToken(), 'user_id' => $request->user()?->id]);
        } catch (\Throwable $e) {
            \Log::info('Me endpoint error', ['err' => $e->getMessage()]);
        }

        return response()->json($request->user());
    }
}
