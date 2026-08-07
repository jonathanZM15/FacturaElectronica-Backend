<?php

namespace Tests\Unit;

use App\Services\SriSignatureService;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SriSignatureServiceTest extends TestCase
{
    public function test_it_normalizes_hexadecimal_x509_serial_numbers_to_decimal(): void
    {
        $service = new SriSignatureService();
        $method = new \ReflectionMethod($service, 'normalizeSerialNumber');
        $method->setAccessible(true);

        $this->assertSame(
            '312711217100771915607426795927698173992237136496',
            $method->invoke($service, '0x36C674B8DA0F7303566BD66BD644F38A3EB1FA70')
        );
    }

    public function test_it_signs_xml_with_a_pkcs12_certificate(): void
    {
        $opensslBin = $this->resolveOpenSslBinary();
        if ($opensslBin === null) {
            $this->markTestSkipped('No se encontró un binario openssl disponible para generar el P12 de prueba.');
        }

        $opensslConfig = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'openssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';

        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sri-signature-' . bin2hex(random_bytes(4));
        $this->assertTrue(mkdir($tempDir));

        $keyFile = $tempDir . DIRECTORY_SEPARATOR . 'key.pem';
        $certFile = $tempDir . DIRECTORY_SEPARATOR . 'cert.pem';
        $p12File = $tempDir . DIRECTORY_SEPARATOR . 'test.p12';
        $password = 'secret123';

        try {
            $generateCert = new Process([
                $opensslBin,
                'req',
                '-x509',
                '-newkey',
                'rsa:2048',
                '-nodes',
                '-keyout',
                $keyFile,
                '-out',
                $certFile,
                '-days',
                '1',
                '-subj',
                '/CN=Test SRI',
            ]);
            $generateCert->setEnv(['OPENSSL_CONF' => $opensslConfig]);
            $generateCert->setTimeout(60);
            $generateCert->run();

            if (!$generateCert->isSuccessful()) {
                $this->markTestSkipped('No se pudo generar el certificado de prueba: ' . $generateCert->getErrorOutput());
            }

            $exportP12 = new Process([
                $opensslBin,
                'pkcs12',
                '-export',
                '-out',
                $p12File,
                '-inkey',
                $keyFile,
                '-in',
                $certFile,
                '-passout',
                'pass:' . $password,
            ]);
            $exportP12->setEnv(['OPENSSL_CONF' => $opensslConfig]);
            $exportP12->setTimeout(60);
            $exportP12->run();

            if (!$exportP12->isSuccessful()) {
                $this->markTestSkipped('No se pudo exportar el P12 de prueba: ' . $exportP12->getErrorOutput());
            }

            $xml = '<?xml version="1.0" encoding="UTF-8"?><factura id="comprobante" version="2.1.0"><infoTributaria><claveAcceso>1234567890123456789012345678901234567890123456789</claveAcceso></infoTributaria><infoFactura /><detalles /></factura>';
            $service = new SriSignatureService();
            $signed = $service->firmarXml($xml, $p12File, $password);

            $this->assertStringContainsString('<ds:Signature', $signed);
            $this->assertStringContainsString('xades:SignedProperties', $signed);

            $domSigned = new \DOMDocument();
            $domSigned->loadXML($signed);

            $xpath = new \DOMXPath($domSigned);
            $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
            $xpath->registerNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');

            $references = $xpath->query('//ds:Signature/ds:SignedInfo/ds:Reference');
            $this->assertNotFalse($references);
            $this->assertGreaterThanOrEqual(2, $references->length);

            $originalDom = new \DOMDocument();
            $originalDom->loadXML($xml);
            $expectedRootDigest = base64_encode(hash('sha1', $originalDom->documentElement->C14N(false, false), true));
            $this->assertSame($expectedRootDigest, $xpath->evaluate('string(//ds:Signature/ds:SignedInfo/ds:Reference[1]/ds:DigestValue)'));

            $signedPropsNode = $xpath->query('//xades:SignedProperties')->item(0);
            $this->assertNotNull($signedPropsNode);
            $expectedSignedPropsDigest = base64_encode(hash('sha1', $signedPropsNode->C14N(false, false), true));
            $this->assertSame($expectedSignedPropsDigest, $xpath->evaluate('string(//ds:Signature/ds:SignedInfo/ds:Reference[2]/ds:DigestValue)'));
        } finally {
            foreach ([$keyFile, $certFile, $p12File] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }
        }
    }

    private function resolveOpenSslBinary(): ?string
    {
        $candidates = [
            'C:\\xampp\\apache\\bin\\openssl.exe',
            'C:\\xampp\\php\\openssl.exe',
            'C:\\Program Files\\Git\\usr\\bin\\openssl.exe',
            'C:\\Program Files (x86)\\Git\\usr\\bin\\openssl.exe',
            'openssl.exe',
            'openssl',
        ];

        foreach ($candidates as $candidate) {
            $process = new Process([$candidate, 'version']);
            $process->setTimeout(10);
            $process->run();

            if ($process->isSuccessful()) {
                return $candidate;
            }
        }

        return null;
    }
}
