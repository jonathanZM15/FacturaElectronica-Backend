<?php

namespace Tests\Unit;

use App\Services\SriSignatureService;
use PHPUnit\Framework\TestCase;

class SriSignatureServiceTest extends TestCase
{
    public function test_it_signs_xml_with_a_pkcs12_certificate(): void
    {
        if (!function_exists('openssl_pkey_new')) {
            $this->markTestSkipped('OpenSSL no disponible en el entorno de pruebas.');
        }

        $opensslConfig = tempnam(sys_get_temp_dir(), 'openssl-');
        $this->assertNotFalse($opensslConfig);
        file_put_contents($opensslConfig, <<<'CNF'
openssl_conf = openssl_init

[openssl_init]
providers = provider_sect

[provider_sect]
default = default_sect

[default_sect]
activate = 1
CNF);

        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => $opensslConfig,
        ]);

        if ($privateKey === false) {
            $this->markTestSkipped('No se pudo generar una clave privada OpenSSL en este entorno.');
        }

        $csr = openssl_csr_new([
            'commonName' => 'Test SRI',
        ], $privateKey, ['digest_alg' => 'sha256', 'config' => $opensslConfig]);

        if ($csr === false) {
            $this->markTestSkipped('No se pudo generar el CSR OpenSSL en este entorno.');
        }

        $certificate = openssl_csr_sign($csr, null, $privateKey, 365, ['digest_alg' => 'sha256']);
        if ($certificate === false) {
            $this->markTestSkipped('No se pudo firmar el certificado OpenSSL en este entorno.');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'sri-p12-');
        if ($tempFile === false) {
            $this->markTestSkipped('No se pudo crear el archivo temporal P12.');
        }

        $password = 'secret123';

        try {
            $exported = openssl_pkcs12_export_to_file($certificate, $tempFile, $privateKey, $password, [
                'friendly_name' => 'test-sri',
            ]);

            $this->assertTrue($exported);

            $xml = '<?xml version="1.0" encoding="UTF-8"?><factura id="comprobante" version="2.1.0"><infoTributaria><claveAcceso>1234567890123456789012345678901234567890123456789</claveAcceso></infoTributaria><infoFactura /><detalles /></factura>';
            $service = new SriSignatureService();
            $signed = $service->firmarXml($xml, $tempFile, $password);

            $this->assertStringContainsString('<ds:Signature', $signed);
            $this->assertStringContainsString('xades:SignedProperties', $signed);
        } finally {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }

            if (is_file($opensslConfig)) {
                unlink($opensslConfig);
            }
        }
    }
}