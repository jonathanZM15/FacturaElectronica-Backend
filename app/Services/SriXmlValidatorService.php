<?php

namespace App\Services;

use DOMDocument;

class SriXmlValidatorService
{
    public function validateFactura(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $dom = new DOMDocument('1.0', 'UTF-8');
            if (!$dom->loadXML($xml, LIBXML_NONET) || $dom->doctype) {
                return ['valid' => false, 'errors' => ['XML mal formado o con una declaración de entidades no permitida.']];
            }
            $version = $dom->documentElement?->getAttribute('version');
            if ($dom->documentElement?->nodeName !== 'factura' || !in_array($version, ['1.1.0', '2.1.0'], true)) {
                return ['valid' => false, 'errors' => ['Se requiere factura con versión soportada 1.1.0 o 2.1.0.']];
            }
            $schema = dirname(__DIR__, 2).'/resources/sri/xsd/factura_V'.$version.'.xsd';
            if (!is_file($schema)) return ['valid' => false, 'errors' => ['No está instalado el esquema XSD oficial de factura.']];
            $valid = $dom->schemaValidate($schema);
            $errors = array_map(fn ($error) => 'Línea '.$error->line.': '.trim($error->message), libxml_get_errors());
            return ['valid' => $valid, 'errors' => array_slice($errors, 0, 15)];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
