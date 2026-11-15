<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;

class SriAuthorizationEvidence
{
    /** Validate evidence before any AUTORIZADO transition, including normalized/mocked responses. */
    public function errors(array $response, ?string $expectedKey = null, ?string $expectedEnvironment = null, ?string $expectedSignedXml = null): array
    {
        $errors = [];
        $number = trim((string) ($response['numero_autorizacion'] ?? ''));
        $date = trim((string) ($response['fecha_autorizacion'] ?? ''));
        $consulted = trim((string) ($response['clave_consultada'] ?? ''));
        if (!preg_match('/^[0-9]{49}$/D', $number) || ($expectedKey !== null && $number !== $expectedKey)) {
            $errors[] = 'El número de autorización no coincide con la clave del comprobante.';
        } elseif ((new SriClaveAccesoService())->digitoVerificadorModulo11(substr($number, 0, 48)) !== substr($number, 48, 1)) {
            $errors[] = 'La clave retornada tiene un dígito verificador inconsistente.';
        }
        if ($expectedKey !== null && $consulted !== $expectedKey) {
            $errors[] = 'La respuesta no identifica la misma clave de acceso consultada.';
        }
        $validDate = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.[0-9]+)?(?:Z|[+-][0-9]{2}:[0-9]{2})$/D', $date) === 1;
        try {
            if ($validDate) {
                $authorizationDate = new \DateTimeImmutable($date);
                $dateErrors = \DateTimeImmutable::getLastErrors();
                $validDate = !$dateErrors || (!$dateErrors['warning_count'] && !$dateErrors['error_count']);
            }
        } catch (\Throwable $e) {
            $validDate = false;
        }
        if (!$validDate) $errors[] = 'La fecha de autorización está ausente o no es una fecha válida con zona horaria.';

        $environment = self::environment((string) ($response['ambiente'] ?? ''));
        if ($environment === null || ($expectedEnvironment !== null && $environment !== self::environment($expectedEnvironment))) {
            $errors[] = 'El ambiente devuelto no corresponde al comprobante consultado.';
        }
        $xml = (string) ($response['xml_autorizado'] ?? '');
        $dom = $this->document($xml);
        if (!$dom || $dom->documentElement?->nodeName !== 'factura') {
            $errors[] = 'La respuesta no contiene un XML de factura válido y completo.';
            return $errors;
        }
        $validation = (new SriXmlValidatorService())->validateFactura($xml);
        if (!$validation['valid']) $errors[] = 'El XML retornado no supera la validación fiscal local.';
        $signature = (new SriSignatureService())->verificarXmlFirmado($xml);
        if (!$signature['valida']) $errors[] = 'La firma del XML retornado no supera la verificación de integridad.';

        $xp = new DOMXPath($dom);
        $key = $this->value($xp, '/factura/infoTributaria/claveAcceso');
        if ($key !== $number || ($expectedKey !== null && $key !== $expectedKey)) {
            $errors[] = 'La clave del XML retornado no coincide con la autorización de esta factura.';
        }
        $identity = [
            '/factura/infoTributaria/ruc' => substr($number, 10, 13),
            '/factura/infoTributaria/ambiente' => substr($number, 23, 1),
            '/factura/infoTributaria/codDoc' => substr($number, 8, 2),
            '/factura/infoTributaria/estab' => substr($number, 24, 3),
            '/factura/infoTributaria/ptoEmi' => substr($number, 27, 3),
            '/factura/infoTributaria/secuencial' => substr($number, 30, 9),
        ];
        foreach ($identity as $path => $expected) {
            if ($this->value($xp, $path) !== $expected) $errors[] = 'La identidad fiscal del XML retornado es inconsistente.';
        }
        if ($environment !== $this->value($xp, '/factura/infoTributaria/ambiente')) $errors[] = 'El ambiente del XML y el de la respuesta son distintos.';
        if (str_replace('/', '', $this->value($xp, '/factura/infoFactura/fechaEmision')) !== substr($number, 0, 8)) {
            $errors[] = 'La fecha de emisión del XML y la clave de acceso son distintas.';
        }
        $issueDate = \DateTimeImmutable::createFromFormat('!dmY', substr($number, 0, 8), new \DateTimeZone('America/Guayaquil'));
        $issueErrors = \DateTimeImmutable::getLastErrors();
        if (!$issueDate || ($issueErrors && ($issueErrors['warning_count'] || $issueErrors['error_count']))) {
            $errors[] = 'La clave no contiene una fecha de emisión válida.';
        } elseif ($validDate && $authorizationDate < $issueDate) {
            $errors[] = 'La autorización no puede preceder a la fecha de emisión.';
        }

        if ($expectedSignedXml !== null) {
            $expectedDocument = $this->document($expectedSignedXml);
            if (!$expectedDocument || !hash_equals(hash('sha256', $expectedDocument->C14N()), hash('sha256', $dom->C14N()))) {
                $errors[] = 'El comprobante retornado no coincide íntegramente con el XML firmado enviado.';
            }
        }
        return array_values(array_unique($errors));
    }

    public static function environment(string $value): ?string
    {
        return match (mb_strtoupper(trim($value))) {
            '1', 'PRUEBAS' => '1',
            '2', 'PRODUCCION', 'PRODUCCIÓN' => '2',
            default => null,
        };
    }

    private function value(DOMXPath $xp, string $path): string
    {
        $nodes = $xp->query($path);
        return $nodes->length === 1 ? trim($nodes->item(0)->textContent) : '';
    }

    private function document(string $xml): ?DOMDocument
    {
        if ($xml === '' || strlen($xml) > 320000) return null;
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $doc = new DOMDocument();
            $doc->preserveWhiteSpace = true;
            return $doc->loadXML($xml, LIBXML_NONET) && !$doc->doctype ? $doc : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
