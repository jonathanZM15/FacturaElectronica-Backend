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
        $this->stateMachine->assertTransition($comprobante->estado_sri, $estado);

        return DB::transaction(function () use ($comprobante, $estado, $attributes, $log) {
            $comprobante->forceFill(array_merge(['estado_sri' => $estado], $attributes));
            $comprobante->save();

            if ($log !== []) {
                ComprobanteSriLog::create(array_merge([
                    'comprobante_id' => $comprobante->id,
                    'etapa' => $log['etapa'] ?? 'estado',
                    'estado' => $estado,
                    'codigo' => $log['codigo'] ?? null,
                    'mensaje' => $log['mensaje'] ?? null,
                    'solicitud_payload' => $log['solicitud_payload'] ?? null,
                    'respuesta_payload' => $log['respuesta_payload'] ?? null,
                    'detalles' => $log['detalles'] ?? null,
                ], []));
            }

            return $comprobante->refresh();
        });
    }

    public function log(Comprobante $comprobante, string $etapa, array $data = []): ComprobanteSriLog
    {
        return ComprobanteSriLog::create(array_merge([
            'comprobante_id' => $comprobante->id,
            'etapa' => $etapa,
            'estado' => $data['estado'] ?? $comprobante->estado_sri,
            'codigo' => $data['codigo'] ?? null,
            'mensaje' => $data['mensaje'] ?? null,
            'solicitud_payload' => $data['solicitud_payload'] ?? null,
            'respuesta_payload' => $data['respuesta_payload'] ?? null,
            'detalles' => $data['detalles'] ?? null,
        ], []));
    }
}