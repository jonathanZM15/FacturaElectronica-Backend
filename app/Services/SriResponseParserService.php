<?php

namespace App\Services;

class SriResponseParserService
{
    public function parseRecepcion(mixed $response): array
    {
        $estado = $this->stringValue($this->get($response, ['RespuestaRecepcionComprobante', 'estado']));
        $comprobante = $this->firstNode($this->get($response, ['RespuestaRecepcionComprobante', 'comprobantes', 'comprobante']));
        $mensajes = $this->toArray($this->get($comprobante, ['mensajes', 'mensaje']));

        return [
            'estado' => $estado ?: 'DESCONOCIDO',
            'success' => $estado === 'RECIBIDA',
            'errores' => $this->mapMensajes($mensajes),
            'raw' => $response,
        ];
    }

    public function parseAutorizacion(mixed $response): array
    {
        $autorizacion = $this->firstNode($this->get($response, ['RespuestaAutorizacionComprobante', 'autorizaciones', 'autorizacion']));
        $estado = $this->stringValue($this->get($autorizacion, ['estado']));

        $payload = [
            'estado' => $estado ?: 'DESCONOCIDO',
            'success' => $estado === 'AUTORIZADO',
            'numero_autorizacion' => $this->stringValue($this->get($autorizacion, ['numeroAutorizacion'])),
            'fecha_autorizacion' => $this->stringValue($this->get($autorizacion, ['fechaAutorizacion'])),
            'xml_autorizado' => $this->stringValue($this->get($autorizacion, ['comprobante'])),
            'errores' => $this->mapMensajes($this->toArray($this->get($autorizacion, ['mensajes', 'mensaje']))),
            'raw' => $response,
        ];

        if ($payload['success']) {
            return $payload;
        }

        if ($payload['estado'] === 'NO AUTORIZADO') {
            $payload['estado'] = 'NO_AUTORIZADO';
        }

        return $payload;
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
            return $node[0] ?? null;
        }

        return $node;
    }

    private function toArray(mixed $node): array
    {
        if ($node === null) {
            return [];
        }

        if (is_array($node)) {
            return $node;
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