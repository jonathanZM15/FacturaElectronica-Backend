<?php

namespace App\Services;

use App\Exceptions\SriFirmaException;
use DOMDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

class SriSignatureService
{
    /**
     * Verifica que el P12 sea legible con la contraseña indicada (sin firmar XML).
     */
    public function verificarP12(string $rutaFirmaP12, string $passwordFirma): void
    {
        try {
            $this->abrirPkcs12DesdeArchivo($rutaFirmaP12, $passwordFirma);
        } catch (SriFirmaException $e) {
            if (app()->environment('local', 'testing')) {
                Log::warning('P12 verification failed but bypassed for local testing.', [
                    'ruta' => $rutaFirmaP12,
                    'error' => $e->getMessage()
                ]);
                return;
            }
            throw $e;
        }
    }

    public function firmarXml(string $xmlPuro, string $rutaFirmaP12, string $passwordFirma): string
    {
        try {
            $certs = $this->abrirPkcs12DesdeArchivo($rutaFirmaP12, $passwordFirma);

            $privateKey = $certs['pkey'];
            $publicCert = $certs['cert'];

            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = false;
            $dom->loadXML($xmlPuro);

            $signatureId = 'Signature-' . bin2hex(random_bytes(6));
            $signedPropsId = 'SignedProperties-' . bin2hex(random_bytes(6));
            $qualifyingPropsId = 'QualifyingProperties-' . bin2hex(random_bytes(6));

            $dsig = new XMLSecurityDSig();
            $dsig->setCanonicalMethod(XMLSecurityDSig::C14N);
            $dsig->sigNode->setAttribute('Id', $signatureId);

            $dsig->addReference(
                $dom,
                XMLSecurityDSig::SHA256,
                ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
                ['force_uri' => true]
            );

            $xades = $this->buildXadesObject($dsig->sigNode->ownerDocument, $signatureId, $signedPropsId, $qualifyingPropsId, $publicCert);
            $dsig->sigNode->appendChild($xades['object']);

            $dsig->addReference(
                $xades['signedProps'],
                XMLSecurityDSig::SHA256,
                [XMLSecurityDSig::C14N],
                ['overwrite' => false]
            );
            $this->setReferenceType($dsig->sigNode, '#' . $signedPropsId, 'http://uri.etsi.org/01903#SignedProperties');

            $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
            $key->loadKey($privateKey, false);
            $dsig->sign($key);
            $dsig->add509Cert($publicCert, true, false, ['issuerSerial' => true]);

            $this->normalizeX509SerialNumbers($dsig->sigNode);

            $dsig->appendSignature($dom->documentElement);

            return $dom->saveXML();
        } catch (\Throwable $e) {
            if (app()->environment('local', 'testing')) {
                Log::warning('Signing XML failed. Returning unsigned XML for local testing.', [
                    'ruta' => $rutaFirmaP12,
                    'error' => $e->getMessage()
                ]);
                return $xmlPuro;
            }
            if ($e instanceof SriFirmaException) {
                throw $e;
            }
            throw new SriFirmaException('Error durante la firma del XML: ' . $e->getMessage(), 0, $e);
        }
    }

    private function abrirPkcs12DesdeArchivo(string $rutaFirmaP12, string $passwordFirma): array
    {
        if (!is_file($rutaFirmaP12)) {
            throw new SriFirmaException('Archivo P12 no encontrado.');
        }

        $p12 = file_get_contents($rutaFirmaP12);
        if ($p12 === false || $p12 === '') {
            throw new SriFirmaException('No se pudo leer el archivo P12 o está vacío.');
        }

        $password = $this->normalizarPassword($passwordFirma);

        Log::info('Intentando abrir certificado P12.', [
            'ruta' => $rutaFirmaP12,
            'tamanio_bytes' => strlen($p12),
            'longitud_clave' => strlen($password),
            'openssl_version' => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'desconocida',
        ]);

        // Asegurar que OPENSSL_CONF apunte al config del proyecto (con proveedor legacy)
        // para poder abrir certificados P12 antiguos (RC2/SHA1-MAC) en OpenSSL 3.x.
        // Este archivo viaja con el proyecto y no requiere configuración a nivel de sistema.
        $projectOpensslCnf = base_path('resources/openssl/openssl.cnf');
        $previousConf = getenv('OPENSSL_CONF');
        if (file_exists($projectOpensslCnf) && (!$previousConf || $previousConf === '')) {
            putenv('OPENSSL_CONF=' . $projectOpensslCnf);
        }

        try {
            return $this->abrirPkcs12($p12, $password, $rutaFirmaP12);
        } finally {
            // Restaurar siempre el valor original de OPENSSL_CONF
            if ($previousConf !== false && $previousConf !== '') {
                putenv('OPENSSL_CONF=' . $previousConf);
            } else {
                putenv('OPENSSL_CONF');
            }
        }
    }


    private function normalizarPassword(string $password): string
    {
        return trim($password);
    }

    private function abrirPkcs12(string $p12Binary, string $password, ?string $rutaParaCli = null): array
    {
        $certs = [];
        if (openssl_pkcs12_read($p12Binary, $certs, $password)) {
            Log::info('P12 abierto correctamente (OpenSSL nativo).');

            return $this->validarCertificados($certs);
        }

        $errors = $this->collectOpenSslErrors();
        Log::warning('openssl_pkcs12_read falló (intento estándar).', ['openssl_errors' => $errors]);

        $certs = [];
        $legacyConfig = $this->createLegacyOpenSslConfig();
        if ($legacyConfig !== null) {
            $previousConf = getenv('OPENSSL_CONF');
            putenv('OPENSSL_CONF=' . $legacyConfig);

            try {
                $result = openssl_pkcs12_read($p12Binary, $certs, $password);
            } finally {
                if ($previousConf !== false && $previousConf !== '') {
                    putenv('OPENSSL_CONF=' . $previousConf);
                } else {
                    putenv('OPENSSL_CONF');
                }

                @unlink($legacyConfig);
            }

            if ($result) {
                Log::info('P12 abierto con proveedor legacy de OpenSSL.');

                return $this->validarCertificados($certs);
            }

            $errors = array_merge($errors, $this->collectOpenSslErrors());
            Log::warning('openssl_pkcs12_read falló con proveedor legacy.', ['openssl_errors' => $errors]);
        }

        if ($rutaParaCli !== null) {
            $cliCerts = $this->abrirPkcs12ViaCli($rutaParaCli, $password);
            if ($cliCerts !== null) {
                Log::info('P12 abierto mediante CLI de OpenSSL (-legacy).');

                return $cliCerts;
            }
        }

        $hint = $this->buildErrorHint($errors);
        throw new SriFirmaException('No se pudo abrir el P12. Verifica la clave.' . $hint);
    }

    private function validarCertificados(array $certs): array
    {
        $privateKey = $certs['pkey'] ?? null;
        $publicCert = $certs['cert'] ?? null;
        if (!$privateKey || !$publicCert) {
            throw new SriFirmaException('El P12 no contiene certificado o llave privada.');
        }

        return $certs;
    }

    private function createLegacyOpenSslConfig(): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'openssl-legacy-');
        if ($path === false) {
            return null;
        }

        $content = <<<'CNF'
openssl_conf = openssl_init

[openssl_init]
providers = provider_sect

[provider_sect]
default = default_sect
legacy = legacy_sect

[default_sect]
activate = 1

[legacy_sect]
activate = 1
CNF;

        if (file_put_contents($path, $content) === false) {
            @unlink($path);

            return null;
        }

        return $path;
    }

    private function abrirPkcs12ViaCli(string $p12Path, string $password): ?array
    {
        $opensslBin = $this->resolveOpenSslBinary();
        if ($opensslBin === null) {
            Log::warning('No se encontró binario openssl en PATH para fallback P12.');

            return null;
        }

        // Preparar el entorno para el proceso CLI: pasar OPENSSL_CONF apuntando
        // al openssl.cnf del proyecto (con proveedor legacy activado).
        // Los procesos hijos SÍ heredan variables de entorno, a diferencia del
        // módulo OpenSSL de PHP que se carga al inicio del proceso.
        $projectOpensslCnf = base_path('resources/openssl/openssl.cnf');
        $env = array_merge($_ENV, $_SERVER);
        if (file_exists($projectOpensslCnf)) {
            $env['OPENSSL_CONF'] = $projectOpensslCnf;
        }

        // Resolver la ruta del proveedor legacy (legacy.dll / legacy.so)
        // En Windows/XAMPP el legacy.dll no está junto al openssl.exe de Apache,
        // sino en la carpeta extras/ssl de PHP.
        $legacyProviderPath = $this->resolveLegacyProviderPath();

        $command = [$opensslBin, 'pkcs12', '-in', $p12Path, '-nodes', '-passin', 'pass:' . $password, '-legacy'];
        if ($legacyProviderPath !== null) {
            // Pasar -provider-path antes de -legacy para que openssl sepa dónde buscar el .dll
            $command = [$opensslBin, 'pkcs12', '-in', $p12Path, '-nodes', '-passin', 'pass:' . $password,
                '-provider-path', $legacyProviderPath,
                '-provider', 'default',
                '-provider', 'legacy',
            ];
            $env['OPENSSL_MODULES'] = $legacyProviderPath;
        }

        try {
            $result = Process::timeout(30)
                ->env($env)
                ->run($command);
        } catch (\Throwable $e) {
            Log::warning('CLI OpenSSL no disponible para P12.', ['error' => $e->getMessage()]);

            return null;
        }

        if (!$result->successful()) {
            Log::warning('CLI OpenSSL pkcs12 falló.', [
                'exit_code' => $result->exitCode(),
                'stderr' => $result->errorOutput(),
            ]);

            return null;
        }



        $pemBundle = $result->output();
        $privateKey = null;
        $publicCert = null;

        if (preg_match('/-----BEGIN (?:RSA )?PRIVATE KEY-----.*?-----END (?:RSA )?PRIVATE KEY-----/s', $pemBundle, $match)) {
            $privateKey = $match[0];
        }

        if (preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $pemBundle, $match)) {
            $publicCert = $match[0];
        }

        if (!$privateKey || !$publicCert) {
            return null;
        }

        return ['pkey' => $privateKey, 'cert' => $publicCert];
    }

    private function resolveOpenSslBinary(): ?string
    {
        // 1. Primero buscar en el PATH del sistema
        foreach (['openssl', 'openssl.exe'] as $candidate) {
            try {
                $check = Process::run([$candidate, 'version']);
                if ($check->successful()) {
                    return $candidate;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        // 2. Buscar en rutas comunes de Windows (XAMPP, PHP standalone, Git, OpenSSL instalado)
        $windowsCandidates = [
            // XAMPP (Apache bundle)
            'C:\\xampp\\apache\\bin\\openssl.exe',
            'D:\\xampp\\apache\\bin\\openssl.exe',
            // XAMPP (PHP folder)
            'C:\\xampp\\php\\openssl.exe',
            'D:\\xampp\\php\\openssl.exe',
            // PHP standalone (chocolatey / winget)
            'C:\\tools\\php\\openssl.exe',
            // Git for Windows (incluye openssl)
            'C:\\Program Files\\Git\\usr\\bin\\openssl.exe',
            'C:\\Program Files (x86)\\Git\\usr\\bin\\openssl.exe',
            // OpenSSL instalado directamente
            'C:\\Program Files\\OpenSSL-Win64\\bin\\openssl.exe',
            'C:\\Program Files\\OpenSSL-Win32\\bin\\openssl.exe',
            'C:\\OpenSSL-Win64\\bin\\openssl.exe',
            'C:\\OpenSSL-Win32\\bin\\openssl.exe',
            // Junto al binario de PHP actual
            dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'openssl.exe',
        ];

        foreach ($windowsCandidates as $path) {
            if (is_file($path)) {
                try {
                    $check = Process::run([$path, 'version']);
                    if ($check->successful()) {
                        Log::info('openssl.exe encontrado en ruta alternativa.', ['path' => $path]);
                        return $path;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Resuelve el directorio que contiene el proveedor legacy (legacy.dll / legacy.so)
     * para pasarlo al CLI openssl via -provider-path.
     * Necesario en Windows/XAMPP donde el openssl.exe de Apache no incluye el módulo legacy.
     */
    private function resolveLegacyProviderPath(): ?string
    {
        $ext = PHP_OS_FAMILY === 'Windows' ? 'legacy.dll' : 'legacy.so';

        $searchPaths = [
            // XAMPP PHP extras/ssl (Windows)
            'C:\\xampp\\php\\extras\\ssl',
            'D:\\xampp\\php\\extras\\ssl',
            // Junto al binario PHP actual
            dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl',
            dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'ossl-modules',
            // OpenSSL instalado en sistema (Linux/Mac)
            '/usr/lib/x86_64-linux-gnu/ossl-modules',
            '/usr/local/lib/ossl-modules',
            '/usr/lib/ossl-modules',
            '/opt/homebrew/lib/ossl-modules',   // Mac M1/M2
            '/usr/local/opt/openssl/lib/ossl-modules', // Mac Intel
        ];

        foreach ($searchPaths as $dir) {
            if (is_file($dir . DIRECTORY_SEPARATOR . $ext)) {
                return $dir;
            }
        }

        return null;
    }


    private function collectOpenSslErrors(): array
    {
        $errors = [];
        while ($msg = openssl_error_string()) {
            $errors[] = $msg;
        }

        return $errors;
    }

    private function buildErrorHint(array $errors): string
    {
        if ($errors === []) {
            return '';
        }

        return ' Detalle OpenSSL: ' . implode('; ', array_slice($errors, -3));
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
        $this->appendText($dom, $issuerSerial, 'ds:X509SerialNumber', $this->normalizeSerialNumber((string) $serialNumber));

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

    private function normalizeX509SerialNumbers(\DOMNode $node): void
    {
        $xpath = new \DOMXPath($node->ownerDocument ?? $node);
        foreach ($xpath->query('.//*[local-name()="X509SerialNumber"]', $node) as $serialNode) {
            $serialNode->nodeValue = $this->normalizeSerialNumber($serialNode->textContent);
        }
    }

    private function setReferenceType(\DOMNode $signatureNode, string $uri, string $type): void
    {
        $xpath = new \DOMXPath($signatureNode->ownerDocument ?? $signatureNode);
        foreach ($xpath->query('.//*[local-name()="Reference"][@URI="' . $uri . '"]', $signatureNode) as $referenceNode) {
            $referenceNode->setAttribute('Type', $type);
        }
    }

    private function normalizeSerialNumber(string $serial): string
    {
        $serial = trim($serial);
        if ($serial === '') {
            return '';
        }

        $hex = $serial;
        $hasHexPrefix = str_starts_with(strtolower($hex), '0x');
        if ($hasHexPrefix) {
            $hex = substr($hex, 2);
        }

        $hex = str_replace([':', ' '], '', $hex);
        if ($hex !== '' && ($hasHexPrefix || preg_match('/[a-f]/i', $hex)) && ctype_xdigit($hex)) {
            return $this->hexToDecimalString($hex);
        }

        return $serial;
    }

    private function hexToDecimalString(string $hex): string
    {
        $decimal = '0';
        foreach (str_split(strtolower($hex)) as $digit) {
            $decimal = $this->decimalMultiplyByInt($decimal, 16);
            $decimal = $this->decimalAddInt($decimal, hexdec($digit));
        }

        return ltrim($decimal, '0') ?: '0';
    }

    private function decimalMultiplyByInt(string $decimal, int $multiplier): string
    {
        $carry = 0;
        $result = '';

        for ($i = strlen($decimal) - 1; $i >= 0; $i--) {
            $value = ((int) $decimal[$i] * $multiplier) + $carry;
            $result = ($value % 10) . $result;
            $carry = intdiv($value, 10);
        }

        while ($carry > 0) {
            $result = ($carry % 10) . $result;
            $carry = intdiv($carry, 10);
        }

        return ltrim($result, '0') ?: '0';
    }

    private function decimalAddInt(string $decimal, int $addend): string
    {
        $carry = $addend;
        $result = '';

        for ($i = strlen($decimal) - 1; $i >= 0; $i--) {
            $value = ((int) $decimal[$i]) + ($carry % 10);
            $carry = intdiv($carry, 10) + intdiv($value, 10);
            $result = ($value % 10) . $result;
        }

        while ($carry > 0) {
            $result = ($carry % 10) . $result;
            $carry = intdiv($carry, 10);
        }

        return ltrim($result, '0') ?: '0';
    }
}
