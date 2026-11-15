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

    public function enviarComprobante(string $xmlFirmado, ?string $ambiente = null): array
    {
        try {
            if (config('sri.allow_network')) {
                if (strlen($xmlFirmado) > 320000) throw new \RuntimeException('El XML firmado supera el límite individual de 320 KB.');
                $schema = app(SriXmlValidatorService::class)->validateFactura($xmlFirmado);
                $signature = app(SriSignatureService::class)->verificarXmlFirmado($xmlFirmado);
                if (!$schema['valid'] || !$signature['valida']) throw new \RuntimeException('El XML no supera las verificaciones locales de estructura y firma.');
                $doc = new \DOMDocument();
                $doc->loadXML($xmlFirmado, LIBXML_NONET);
                $xp = new \DOMXPath($doc);
                $providers = $xp->query('/factura/infoAdicional/campoAdicional[@nombre="RUC Proveedor"]');
                if (config('sri.proveedor_requerido', true) && ($providers->length !== 1
                    || !preg_match('/^(?!0{13}$)(?!9{13}$)[0-9]{13}$/D', $providers->item(0)?->textContent ?? ''))) {
                    throw new \RuntimeException('El XML conservado no identifica al proveedor del software. Revisa la configuración y la situación fiscal del documento antes de enviarlo.');
                }
                if (trim($xp->evaluate('string(/factura/infoTributaria/contribuyenteRimpe)')) === SriXmlValidatorService::RIMPE_POPULAR
                    && !config('sri.rimpe_popular_habilitado')) throw new \RuntimeException('El perfil RIMPE Popular permanece pendiente de validación externa.');
            }
            $client = $this->buildClient($this->recepcionWsdl($ambiente));

            $response = $client->validarComprobante(['xml' => $xmlFirmado]);

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

    public function autorizarComprobante(string $claveAcceso, ?string $ambiente = null): array
    {
        try {
            $client = $this->buildClient($this->autorizacionWsdl($ambiente));

            $response = $client->autorizacionComprobante(['claveAccesoComprobante' => $claveAcceso]);

            return $this->parser->parseAutorizacion($response, $claveAcceso, $ambiente);
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
        if (!config('sri.allow_network', true)) {
            throw new \RuntimeException('La comunicación con el SRI está deshabilitada en este ambiente de ensayo.');
        }
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

    private function recepcionWsdl(?string $ambiente): string
    {
        return $this->isProduction($ambiente)
            ? config('sri.recepcion_wsdl_produccion')
            : config('sri.recepcion_wsdl_pruebas');
    }

    private function autorizacionWsdl(?string $ambiente): string
    {
        return $this->isProduction($ambiente)
            ? config('sri.autorizacion_wsdl_produccion')
            : config('sri.autorizacion_wsdl_pruebas');
    }
    private function isProduction(?string $ambiente): bool
    {
        return match (strtoupper($ambiente ?? config('sri.environment', 'PRUEBAS'))) {
            'PRODUCCION', 'PRODUCCIÓN', '2' => true,
            'PRUEBAS', '1' => false,
            default => throw new \InvalidArgumentException('Ambiente SRI no reconocido.'),
        };
    }

}
