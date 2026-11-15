<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Conserva los enlaces existentes y consume cada credencial una sola vez. */
class VerificationTokenService
{
    public static function digest(string $token): string
    {
        return 'sha256:'.hash('sha256', $token);
    }

    public function consume(string $rawToken, string $type, callable $action)
    {
        if (str_starts_with($rawToken, 'sha256:')) {
            return response()->json(['message' => 'Enlace inválido'], 400);
        }
        // También reconoce enlaces emitidos por versiones anteriores.
        $query = fn () => DB::table('user_verification_tokens')
            ->whereIn('token', [self::digest($rawToken), $rawToken])->where('type', $type);
        $candidate = $query()->first();
        if (!$candidate) {
            return response()->json(['message' => 'Enlace inválido o expirado', 'code' => 'TOKEN_NOT_FOUND'], 400);
        }
        return DB::transaction(function () use ($query, $candidate, $action) {
            // Mismo orden de bloqueo que la emisión y la invalidación de credenciales.
            $user = User::whereKey($candidate->user_id)->lockForUpdate()->first();
            $token = $query()->lockForUpdate()->first();
            if (!$user || !$token || $token->used || now()->gte($token->expires_at)) {
                return response()->json(['message' => 'Enlace inválido, expirado o ya utilizado', 'code' => 'TOKEN_INVALID'], 400);
            }
            $metadata = json_decode($token->metadata ?? '{}', true);
            $metadata = is_array($metadata) ? $metadata : [];
            $email = $metadata['email_sent_to'] ?? $metadata['old_email'] ?? null;
            if ($email !== null && mb_strtolower($email) !== mb_strtolower($user->email)) {
                return response()->json(['message' => 'Este enlace corresponde a un correo anterior', 'code' => 'TOKEN_EMAIL_MISMATCH'], 400);
            }
            $response = $action($user, $metadata);
            if ($response->getStatusCode() < 400) {
                DB::table('user_verification_tokens')->where('id', $token->id)
                    ->update(['used' => true, 'used_at' => now(), 'updated_at' => now()]);
            }
            return $response;
        }, 3);
    }
}
