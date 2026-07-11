<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;

class SriXmlValidatorService
{
    public function validateFactura(string $xml): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;

        if (!@$dom->loadXML($xml)) {
            return [
                'valid' => false,
                'errors' => ['El XML no es bien formado.'],
            ];
        }

        $xpath = new DOMXPath($dom);
        $errors = [];

        if ($dom->documentElement?->nodeName !== 'factura') {
            $errors[] = 'El nodo raiz debe ser factura.';
        }

        foreach (['infoTributaria/claveAcceso', 'infoTributaria/ruc', 'infoFactura/fechaEmision', 'detalles/detalle'] as $query) {
            if ($xpath->query('//'.$query)->length === 0) {
                $errors[] = 'Falta el nodo obligatorio: ' . $query;
            }
        }

        $claveAcceso = trim((string) $xpath->evaluate('string(//infoTributaria/claveAcceso)'));
        if (strlen($claveAcceso) !== 49) {
            $errors[] = 'La clave de acceso debe tener 49 digitos.';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }
}