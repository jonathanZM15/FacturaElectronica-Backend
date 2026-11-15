<?php

namespace App\Services;

class SriResponseParserService
{
    public function parseRecepcion(mixed $response): array
    {
        $estado = $this->stringValue($this->get($response, ['RespuestaRecepcionComprobante', 'estado']));
        $comprobante = $this->firstNode($this->get($response, ['RespuestaRecepcionComprobante', 'comprobantes', 'comprobante']));
        $mensajes = $this->toArray($this->get($comprobante, ['mensajes', 'mensaje']));
        $errores = $this->mapMensajes($mensajes);

        // P05: Identificar si el SRI tiene la clave en proceso (código 70) o ya registrada (código 43)
        $esClaveEnProceso = false;
        $esClaveRegistrada = false;
        foreach ($errores as $err) {
            $id = (string) ($err['identificador'] ?? '');
            if ($id === '70') {
                $esClaveEnProceso = true;
            } elseif ($id === '43') {
                $esClaveRegistrada = true;
            }
        }

        return [
            'estado' => $estado ?: 'DESCONOCIDO',
            'success' => $estado === 'RECIBIDA',
            'es_clave_en_proceso' => $esClaveEnProceso,
            'es_clave_registrada' => $esClaveRegistrada,
            'errores' => $errores,
            'raw' => $response,
        ];
    }

    public function parseAutorizacion(mixed $response, ?string $expectedClaveAcceso = null, ?string $expectedAmbiente = null): array
    {
        $autorizaciones = $this->toArray($this->get($response, ['RespuestaAutorizacionComprobante', 'autorizaciones', 'autorizacion']));
        $autorizacion = null;

        // P07: Seleccionar el nodo correspondiente a la clave consultada si existen varios
        if ($expectedClaveAcceso !== null && !empty($autorizaciones)) {
            foreach ($autorizaciones as $cand) {
                $numAut = $this->stringValue($this->get($cand, ['numeroAutorizacion']));
                if ($numAut === $expectedClaveAcceso) {
                    $autorizacion = $cand;
                    break;
                }
            }
        }

        if ($autorizacion === null) {
            $autorizacion = $this->firstNode($autorizaciones);
        }

        $rawEstado = strtoupper(trim((string) $this->stringValue($this->get($autorizacion, ['estado']))));
        $numeroAutorizacion = $this->stringValue($this->get($autorizacion, ['numeroAutorizacion']));
        $fechaAutorizacion = $this->stringValue($this->get($autorizacion, ['fechaAutorizacion']));
        $ambiente = $this->stringValue($this->get($autorizacion, ['ambiente']));
        $xmlAutorizado = $this->stringValue($this->get($autorizacion, ['comprobante']));
        $claveConsultada = $this->stringValue($this->get($response, ['RespuestaAutorizacionComprobante', 'claveAccesoConsultada']));
        $errores = $this->mapMensajes($this->toArray($this->get($autorizacion, ['mensajes', 'mensaje'])));

        // Normalización de estados oficiales del SRI (Ficha Técnica F1 sección 7.3)
        $isAutorizado = ($rawEstado === 'AUTORIZADO');
        $isRechazado = in_array($rawEstado, ['NO AUTORIZADO', 'NO_AUTORIZADO', 'RECHAZADO'], true);
        $isEnProceso = in_array($rawEstado, ['EN PROCESO', 'PENDIENTE'], true);

        // A successful status alone is not proof of authorization of this document.
        if ($isAutorizado) {
            $evidenceErrors = (new SriAuthorizationEvidence())->errors([
                'numero_autorizacion' => $numeroAutorizacion, 'fecha_autorizacion' => $fechaAutorizacion,
                'ambiente' => $ambiente, 'xml_autorizado' => $xmlAutorizado, 'clave_consultada' => $claveConsultada,
            ], $expectedClaveAcceso, $expectedAmbiente);
            if ($evidenceErrors) {
                $isAutorizado = false;
                $rawEstado = 'INCONSISTENTE';
                foreach ($evidenceErrors as $message) $errores[] = [
                    'identificador' => 'ERR_EVIDENCIA', 'mensaje' => $message,
                    'informacion_adicional' => null, 'tipo' => 'ERROR',
                ];
            }
        } elseif ($isRechazado && $expectedClaveAcceso !== null && $claveConsultada !== $expectedClaveAcceso) {
            $isRechazado = false;
            $rawEstado = 'INCONSISTENTE';
            $errores[] = ['identificador' => 'ERR_CLAVE_DISCORDANTE', 'mensaje' => 'La respuesta de rechazo corresponde a otra clave consultada.', 'informacion_adicional' => null, 'tipo' => 'ERROR'];
        }

        $estadoFinal = $isAutorizado ? 'AUTORIZADO' : ($isRechazado ? 'NO_AUTORIZADO' : ($isEnProceso ? 'EN_PROCESO' : ($rawEstado ?: 'DESCONOCIDO')));

        return [
            'estado' => $estadoFinal,
            'success' => $isAutorizado,
            'clave_consultada' => $claveConsultada,
            'ambiente' => $ambiente,
            'numero_autorizacion' => $numeroAutorizacion,
            'fecha_autorizacion' => $fechaAutorizacion,
            'xml_autorizado' => $xmlAutorizado,
            'errores' => $errores,
            'raw' => $response,
        ];
    }

    private function mapMensajes(array $mensajes): array
    {
        $errores = [];

        foreach ($mensajes as $mensaje) {
            $errores[] = [
                'identificador' => $this->stringValue($this->get($mensaje, ['identificador'])),
                'mensaje' => $this->stringValue($this->get($mensaje, ['mensaje'])),
                'informacion_adicional' => $this->stringValue($this->get($mensaje, ['informacionAdicional'])),
                'tipo' => $this->stringValue($this->get($mensaje, ['tipo'])),
            ];
        }

        return $errores;
    }

    private function get(mixed $node, array $path): mixed
    {
        foreach ($path as $segment) {
            if (is_array($node) && array_key_exists($segment, $node)) {
                $node = $node[$segment];
                continue;
            }

            if (is_object($node) && isset($node->{$segment})) {
                $node = $node->{$segment};
                continue;
            }

            return null;
        }

        return $node;
    }

    private function firstNode(mixed $node): mixed
    {
        if (is_array($node)) {
            if (array_is_list($node)) {
                return $node[0] ?? null;
            }
            return $node;
        }

        return $node;
    }

    private function toArray(mixed $node): array
    {
        if ($node === null) {
            return [];
        }

        if (is_array($node)) {
            if (array_is_list($node)) {
                return $node;
            }
            return [$node];
        }

        return [$node];
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }
}
