<?php

namespace App\Services;

use SoapClient;
use SoapFault;

class SriSoapService
{
    private const WSDL_RECEPCION = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl';
    private const WSDL_AUTORIZACION = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';

    public function enviarComprobante(string $xmlFirmado): array
    {
        try {
            $client = $this->buildClient(self::WSDL_RECEPCION);
            $xmlBase64 = base64_encode($xmlFirmado);

            $response = $client->validarComprobante(['xml' => $xmlBase64]);

            $estado = $response->RespuestaRecepcionComprobante->estado ?? null;
            if ($estado === 'RECIBIDA') {
                return [
                    'success' => true,
                    'estado' => 'RECIBIDA',
                ];
            }

            $errores = $this->extractRecepcionErrors($response);

            return [
                'success' => false,
                'estado' => $estado ?? 'DESCONOCIDO',
                'errores' => $errores,
            ];
        } catch (SoapFault $e) {
            return [
                'success' => false,
                'estado' => 'SOAP_FAULT',
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'estado' => 'ERROR',
                'error' => $e->getMessage(),
            ];
        }
    }

    public function autorizarComprobante(string $claveAcceso): array
    {
        try {
            $client = $this->buildClient(self::WSDL_AUTORIZACION);

            $response = $client->autorizacionComprobante(['claveAccesoComprobante' => $claveAcceso]);

            $autorizaciones = $response->RespuestaAutorizacionComprobante->autorizaciones->autorizacion ?? null;
            $autorizacion = $this->firstNode($autorizaciones);

            $estado = $autorizacion->estado ?? null;
            if ($estado === 'AUTORIZADO') {
                return [
                    'success' => true,
                    'estado' => 'AUTORIZADO',
                    'numero_autorizacion' => (string) ($autorizacion->numeroAutorizacion ?? $claveAcceso),
                    'fecha_autorizacion' => (string) ($autorizacion->fechaAutorizacion ?? ''),
                    'xml_autorizado' => (string) ($autorizacion->comprobante ?? ''),
                ];
            }

            $motivos = $this->extractAutorizacionErrors($autorizacion);

            return [
                'success' => false,
                'estado' => $estado ?? 'DESCONOCIDO',
                'errores' => $motivos,
            ];
        } catch (SoapFault $e) {
            return [
                'success' => false,
                'estado' => 'SOAP_FAULT',
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'estado' => 'ERROR',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function buildClient(string $wsdl): SoapClient
    {
        return new SoapClient($wsdl, [
            'trace' => false,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'connection_timeout' => 15,
        ]);
    }

    private function extractRecepcionErrors($response): array
    {
        $errores = [];
        $comprobantes = $response->RespuestaRecepcionComprobante->comprobantes->comprobante ?? null;
        $comprobante = $this->firstNode($comprobantes);
        $mensajes = $comprobante->mensajes->mensaje ?? null;

        foreach ($this->toArray($mensajes) as $mensaje) {
            $errores[] = [
                'identificador' => (string) ($mensaje->identificador ?? ''),
                'mensaje' => (string) ($mensaje->mensaje ?? ''),
                'informacion_adicional' => (string) ($mensaje->informacionAdicional ?? ''),
                'tipo' => (string) ($mensaje->tipo ?? ''),
            ];
        }

        return $errores;
    }

    private function extractAutorizacionErrors($autorizacion): array
    {
        $errores = [];
        $mensajes = $autorizacion->mensajes->mensaje ?? null;
        foreach ($this->toArray($mensajes) as $mensaje) {
            $errores[] = [
                'identificador' => (string) ($mensaje->identificador ?? ''),
                'mensaje' => (string) ($mensaje->mensaje ?? ''),
                'informacion_adicional' => (string) ($mensaje->informacionAdicional ?? ''),
                'tipo' => (string) ($mensaje->tipo ?? ''),
            ];
        }

        return $errores;
    }

    private function firstNode($node)
    {
        if (is_array($node)) {
            return $node[0] ?? null;
        }
        return $node;
    }

    private function toArray($node): array
    {
        if ($node === null) {
            return [];
        }
        if (is_array($node)) {
            return $node;
        }
        return [$node];
    }
}
