<?php

namespace App\Services;

use DOMDocument;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

class SriSignatureService
{
    public function firmarXml(string $xmlPuro, string $rutaFirmaP12, string $passwordFirma): string
    {
        if (!is_file($rutaFirmaP12)) {
            throw new \RuntimeException('Archivo P12 no encontrado.');
        }

        $p12 = file_get_contents($rutaFirmaP12);
        if ($p12 === false) {
            throw new \RuntimeException('No se pudo leer el archivo P12.');
        }

        $certs = [];
        if (!openssl_pkcs12_read($p12, $certs, $passwordFirma)) {
            throw new \RuntimeException('No se pudo abrir el P12. Verifica la clave.');
        }

        $privateKey = $certs['pkey'] ?? null;
        $publicCert = $certs['cert'] ?? null;
        if (!$privateKey || !$publicCert) {
            throw new \RuntimeException('El P12 no contiene certificado o llave privada.');
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xmlPuro);

        $signatureId = 'Signature-' . bin2hex(random_bytes(6));
        $signedPropsId = 'SignedProperties-' . bin2hex(random_bytes(6));
        $qualifyingPropsId = 'QualifyingProperties-' . bin2hex(random_bytes(6));

        $xades = $this->buildXadesObject($dom, $signatureId, $signedPropsId, $qualifyingPropsId, $publicCert);

        $dsig = new XMLSecurityDSig();
        $dsig->setCanonicalMethod(XMLSecurityDSig::C14N);
        $dsig->addReference(
            $dom,
            XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['force_uri' => true]
        );
        $dsig->addReference(
            $xades['signedProps'],
            XMLSecurityDSig::SHA256,
            null,
            [
                'uri' => '#' . $signedPropsId,
                'type' => 'http://uri.etsi.org/01903#SignedProperties',
            ]
        );

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($privateKey, false);
        $dsig->sign($key);
        $dsig->add509Cert($publicCert, true, false, ['issuerSerial' => true]);

        $dsig->sigNode->setAttribute('Id', $signatureId);
        $dsig->sigNode->appendChild($xades['object']);

        $dsig->appendSignature($dom->documentElement);

        return $dom->saveXML();
    }

    private function buildXadesObject(
        DOMDocument $dom,
        string $signatureId,
        string $signedPropsId,
        string $qualifyingPropsId,
        string $publicCert
    ): array {
        $object = $dom->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:Object');
        $qualifyingProperties = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:QualifyingProperties');
        $qualifyingProperties->setAttribute('Target', '#' . $signatureId);
        $qualifyingProperties->setAttribute('Id', $qualifyingPropsId);

        $signedProps = $dom->createElement('xades:SignedProperties');
        $signedProps->setAttribute('Id', $signedPropsId);

        $signedSigProps = $dom->createElement('xades:SignedSignatureProperties');
        $this->appendText($dom, $signedSigProps, 'xades:SigningTime', gmdate('c'));

        $signingCertificate = $dom->createElement('xades:SigningCertificate');
        $cert = $dom->createElement('xades:Cert');
        $certDigest = $dom->createElement('xades:CertDigest');
        $digestMethod = $dom->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', XMLSecurityDSig::SHA256);
        $digestValue = $dom->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:DigestValue', $this->certDigest($publicCert));
        $certDigest->appendChild($digestMethod);
        $certDigest->appendChild($digestValue);

        $issuerSerial = $dom->createElement('xades:IssuerSerial');
        $parsed = openssl_x509_parse($publicCert) ?: [];
        $issuerName = $parsed['issuer'] ?? [];
        $issuerText = $this->buildIssuerName($issuerName);
        $serialNumber = $parsed['serialNumber'] ?? '';

        $this->appendText($dom, $issuerSerial, 'ds:X509IssuerName', $issuerText);
        $this->appendText($dom, $issuerSerial, 'ds:X509SerialNumber', (string) $serialNumber);

        $cert->appendChild($certDigest);
        $cert->appendChild($issuerSerial);
        $signingCertificate->appendChild($cert);
        $signedSigProps->appendChild($signingCertificate);
        $signedProps->appendChild($signedSigProps);

        $signedDataObjectProps = $dom->createElement('xades:SignedDataObjectProperties');
        $signedProps->appendChild($signedDataObjectProps);

        $qualifyingProperties->appendChild($signedProps);
        $object->appendChild($qualifyingProperties);

        return [
            'object' => $object,
            'signedProps' => $signedProps,
        ];
    }

    private function appendText(DOMDocument $dom, $parent, string $name, string $value): void
    {
        $node = $dom->createElement($name);
        $node->appendChild($dom->createTextNode($value));
        $parent->appendChild($node);
    }

    private function certDigest(string $pem): string
    {
        $der = $this->pemToDer($pem);
        return base64_encode(hash('sha256', $der, true));
    }

    private function pemToDer(string $pem): string
    {
        $clean = preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $pem);
        return base64_decode($clean) ?: '';
    }

    private function buildIssuerName(array $issuer): string
    {
        $parts = [];
        foreach ($issuer as $key => $value) {
            $parts[] = $key . '=' . $value;
        }
        return implode(', ', $parts);
    }
}
