<?php

namespace App\Services;

use App\Models\Comprobante;
use App\Models\ComprobanteSriLog;
use Illuminate\Support\Facades\DB;

class SriComprobanteLifecycleService
{
    public function __construct(
        private readonly SriStateMachineService $stateMachine
    ) {
    }

    public function transition(Comprobante $comprobante, string $estado, array $attributes = [], array $log = []): Comprobante
    {
        return DB::transaction(function () use ($comprobante, $estado, $attributes, $log) {
            $comprobante = Comprobante::whereKey($comprobante->id)->lockForUpdate()->firstOrFail();

            // P14: Si el estado cambia, validar la transición en la máquina de estados
            if ($comprobante->estado_sri !== $estado) {
                $this->stateMachine->assertTransition($comprobante->estado_sri, $estado);
                $comprobante->estado_sri = $estado;
            }

            // P14: Aplicar atributos (diagnóstico, errores, contadores) aunque el estado se repita
            if ($attributes !== []) {
                $comprobante->forceFill($attributes);
            }
            $comprobante->save();

            // P14: Registrar el intento en el historial sin omitirlo por repetición de estado y protegiendo secretos
            if ($log !== []) {
                ComprobanteSriLog::create([
                    'comprobante_id' => $comprobante->id,
                    'etapa' => $log['etapa'] ?? 'estado',
                    'estado' => $estado,
                    'codigo' => $log['codigo'] ?? null,
                    'mensaje' => $log['mensaje'] ?? null,
                    'solicitud_payload' => $this->stringifyPayload($log['solicitud_payload'] ?? null),
                    'respuesta_payload' => $this->stringifyPayload($log['respuesta_payload'] ?? null),
                    'detalles' => $this->sanitizePayload($log['detalles'] ?? null),
                ]);
            }

            return $comprobante->refresh();
        });
    }

    public function log(Comprobante $comprobante, string $etapa, array $data = []): ComprobanteSriLog
    {
        return ComprobanteSriLog::create([
            'comprobante_id' => $comprobante->id,
            'etapa' => $etapa,
            'estado' => $data['estado'] ?? $comprobante->estado_sri,
            'codigo' => $data['codigo'] ?? null,
            'mensaje' => $data['mensaje'] ?? null,
            'solicitud_payload' => $this->stringifyPayload($data['solicitud_payload'] ?? null),
            'respuesta_payload' => $this->stringifyPayload($data['respuesta_payload'] ?? null),
            'detalles' => $this->sanitizePayload($data['detalles'] ?? null),
        ]);
    }

    /**
     * Serializar payload a string para columnas longText.
     */
    private function stringifyPayload(mixed $payload): ?string
    {
        if ($payload === null) {
            return null;
        }
        $sanitized = $this->sanitizePayload($payload);
        if (is_array($sanitized) || is_object($sanitized)) {
            return json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return (string) $sanitized;
    }

    /**
     * P14: Sanitizar payloads para evitar filtrar contraseñas o claves privadas en logs.
     */
    private function sanitizePayload(mixed $payload): mixed
    {
        if ($payload === null) {
            return null;
        }

        if (is_string($payload)) {
            return preg_replace('/(password|clave|contrasena|private_key)(["\']?\s*[:=]\s*["\']?)([^"\'&<\s]+)/i', '$1$2********', $payload);
        }

        if (is_array($payload)) {
            foreach ($payload as $key => $val) {
                if (in_array(strtolower((string) $key), ['password', 'clave', 'contrasena', 'private_key', 'cert_password', 'encrypted_password'], true)) {
                    $payload[$key] = '********';
                } elseif (is_array($val) || is_string($val)) {
                    $payload[$key] = $this->sanitizePayload($val);
                }
            }
            return $payload;
        }

        return $payload;
    }
}
