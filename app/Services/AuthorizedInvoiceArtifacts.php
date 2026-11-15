<?php

namespace App\Services;

use App\Models\Comprobante;
use DOMDocument;
use DOMXPath;

class AuthorizedInvoiceArtifacts
{
    /** Reconstruct a delivery package using verified stored evidence, never substitute metadata. */
    public function get(Comprobante $invoice): array
    {
        abort_unless($invoice->estado_sri === 'AUTORIZADO', 409, 'El comprobante todavía no está autorizado.');
        $inner = (string) $invoice->xml_autorizado;
        $response = [
            'numero_autorizacion' => $invoice->numero_autorizacion,
            'fecha_autorizacion' => $invoice->fecha_autorizacion?->toIso8601String(),
            'clave_consultada' => $invoice->clave_acceso,
            'ambiente' => $invoice->ambiente,
            'xml_autorizado' => $inner,
        ];
        $storedResponse = json_decode((string) $invoice->ultima_respuesta_autorizacion, true);
        if (!empty($storedResponse['fecha_autorizacion'])) {
            try {
                abort_unless(\Illuminate\Support\Carbon::parse($storedResponse['fecha_autorizacion'])->equalTo($invoice->fecha_autorizacion), 409, 'La fecha conservada no coincide con la respuesta de autorización.');
                $response['fecha_autorizacion'] = $storedResponse['fecha_autorizacion'];
            } catch (\InvalidArgumentException $e) {
                abort(409, 'La fecha original de autorización requiere revisión.');
            }
        }
        $errors = app(SriAuthorizationEvidence::class)->errors(
            $response, $invoice->clave_acceso, $invoice->ambiente, (string) $invoice->xml_firmado
        );
        abort_if($errors !== [], 409, 'La evidencia de autorización requiere revisión. No se generarán documentos con datos sustituidos.');
        $source = new DOMDocument();
        $source->preserveWhiteSpace = true;
        $source->loadXML($inner, LIBXML_NONET);
        $xp = new DOMXPath($source);
        $value = fn (string $path) => trim($xp->evaluate('string('.$path.')'));
        $buyer = $invoice->cliente_snapshot ?: $invoice->cliente?->getAttributes();
        abort_if(
            abs((float) $value('/factura/infoFactura/importeTotal') - (float) $invoice->total) > .001
            || $value('/factura/infoFactura/identificacionComprador') !== (string) ($buyer['identificacion'] ?? '')
            || $value('/factura/infoFactura/razonSocialComprador') !== (string) ($buyer['razon_social'] ?? ''),
            409, 'Los datos conservados de la factura no coinciden con el XML autorizado. Se requiere revisión.'
        );
        $additional = [];
        foreach ($xp->query('/factura/infoAdicional/campoAdicional') as $node) {
            $additional[] = ['nombre' => $node->getAttribute('nombre'), 'valor' => $node->textContent];
        }
        $package = new DOMDocument('1.0', 'UTF-8');
        $root = $package->appendChild($package->createElement('autorizacion'));
        foreach ([
            'estado' => 'AUTORIZADO', 'numeroAutorizacion' => $response['numero_autorizacion'],
            'fechaAutorizacion' => $response['fecha_autorizacion'],
            'ambiente' => SriAuthorizationEvidence::environment($invoice->ambiente) === '1' ? 'PRUEBAS' : 'PRODUCCION',
            'comprobante' => $inner,
        ] as $name => $content) {
            // A text node safely encodes XML characters, including a possible CDATA terminator.
            $element = $root->appendChild($package->createElement($name));
            $element->appendChild($package->createTextNode((string) $content));
        }
        return ['signed_xml' => $inner, 'package_xml' => $package->saveXML(), 'additional' => $additional];
    }
}
