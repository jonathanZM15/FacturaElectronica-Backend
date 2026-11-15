<?php

namespace App\Services;

use DOMDocument;

class SriXmlValidatorService
{
    public const RIMPE = 'CONTRIBUYENTE RÉGIMEN RIMPE';
    public const RIMPE_POPULAR = 'CONTRIBUYENTE NEGOCIO POPULAR - RÉGIMEN RIMPE';

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
            $xpath = new \DOMXPath($dom);
            $rimpe = $xpath->query('/factura/infoTributaria/contribuyenteRimpe');
            if ($rimpe->length && !in_array($rimpe->item(0)->textContent, [self::RIMPE, self::RIMPE_POPULAR], true)) {
                return ['valid' => false, 'errors' => ['La leyenda RIMPE no corresponde al Anexo 22 de la ficha técnica.']];
            }
            $popular = $rimpe->length === 1 && $rimpe->item(0)->textContent === self::RIMPE_POPULAR;
            $valid = $popular ? @$dom->schemaValidateSource($this->rimpe234Schema($schema)) : @$dom->schemaValidate($schema);
            $errors = array_map(fn ($error) => 'Línea '.$error->line.': '.trim($error->message), libxml_get_errors());
            return ['valid' => $valid, 'errors' => array_slice($errors, 0, 15), 'schema_profile' => $popular ? 'SRI_2_34_ANEXO_22' : 'XSD_OFICIAL'];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * The published February 2022 XSD predates the Negocio Popular legend in
     * Annex 22 (September 2022, retained in 2.34). Keep the official file intact;
     * derive only this exact two-value restriction, preserving every other rule.
     */
    private function rimpe234Schema(string $schema): string
    {
        $doc = new DOMDocument();
        if (!$doc->load($schema, LIBXML_NONET) || $doc->doctype) {
            throw new \RuntimeException('No se pudo leer el esquema base de factura.');
        }
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');
        foreach ($xpath->query('/xs:schema/xs:import[@schemaLocation="xmldsig-core-schema.xsd"]') as $import) {
            $dependency = realpath(dirname($schema).'/xmldsig-core-schema.xsd');
            if (!$dependency) throw new \RuntimeException('Falta el esquema local de firma XML.');
            $path = str_replace('\\', '/', $dependency);
            $uri = implode('/', array_map('rawurlencode', explode('/', $path)));
            $import->setAttribute('schemaLocation', (str_starts_with($path, '/') ? 'file://' : 'file:///').str_replace('%3A', ':', $uri));
        }
        $patterns = $xpath->query('/xs:schema/xs:simpleType[@name="contribuyenteRimpe"]/xs:restriction/xs:pattern');
        if ($patterns->length !== 1 || $patterns->item(0)->getAttribute('value') !== self::RIMPE) {
            throw new \RuntimeException('Cambió el esquema RIMPE de referencia. Revisa su compatibilidad antes de aplicar el perfil 2.34.');
        }
        $pattern = $patterns->item(0);
        $restriction = $pattern->parentNode;
        $restriction->removeChild($pattern);
        foreach ([self::RIMPE, self::RIMPE_POPULAR] as $legend) {
            $allowed = $doc->createElementNS('http://www.w3.org/2001/XMLSchema', 'xsd:enumeration');
            $allowed->setAttribute('value', $legend);
            $restriction->appendChild($allowed);
        }
        return $doc->saveXML();
    }
}
