<?php

namespace App\Services;

use SoapClient;
use SoapFault;

class SriSoapService
{
    public function __construct(
        private readonly SriResponseParserService $parser
    ) {
    }

    public function enviarComprobante(string $xmlFirmado): array
    {
        try {
            $client = $this->buildClient($this->recepcionWsdl());
            $xmlBase64 = base64_encode($xmlFirmado);

            $response = $client->validarComprobante(['xml' => $xmlBase64]);

            return $this->parser->parseRecepcion($response);
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
            $client = $this->buildClient($this->autorizacionWsdl());

            $response = $client->autorizacionComprobante(['claveAccesoComprobante' => $claveAcceso]);

            return $this->parser->parseAutorizacion($response);
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
            'trace' => true,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'connection_timeout' => 15,
            'stream_context' => stream_context_create([
                'http' => [
                    'timeout' => 15,
                ],
            ]),
        ]);
    }

    private function recepcionWsdl(): string
    {
        return config('sri.environment') === 'PRODUCCION'
            ? config('sri.recepcion_wsdl_produccion')
            : config('sri.recepcion_wsdl_pruebas');
    }

    private function autorizacionWsdl(): string
    {
        return config('sri.environment') === 'PRODUCCION'
            ? config('sri.autorizacion_wsdl_produccion')
            : config('sri.autorizacion_wsdl_pruebas');
    }
}
