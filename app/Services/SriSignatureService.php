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
            if (str_contains($e->getMessage(), 'CADUCADO')) {
                throw $e;
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

            // ── 1. Extraer datos del certificado ──
            $certData = openssl_x509_parse($publicCert);
            $certDer = $this->pemToDer($publicCert);
            $certDigestValue = base64_encode(sha1($certDer, true));
            $certBase64 = base64_encode($certDer);
            $certFormatted = "\n" . chunk_split($certBase64, 76, "\n");

            $issuerDN = $this->buildIssuerName($certData['issuer'] ?? []);
            $serialNumber = $this->normalizeSerialNumber((string) ($certData['serialNumber'] ?? ''));

            // ── 2. Extraer datos RSA (Modulus y Exponent) ──
            $privateKeyResource = openssl_pkey_get_private($privateKey);
            $keyDetails = openssl_pkey_get_details($privateKeyResource);
            $modulus = "\n" . chunk_split(base64_encode($keyDetails['rsa']['n']), 76, "\n");
            $exponent = base64_encode($keyDetails['rsa']['e']);

            // ── 3. Generar IDs únicos ──
            $certNum = mt_rand(100000, 999999);
            $sigNum  = mt_rand(100000, 999999);

            $signatureId        = "Signature{$sigNum}";
            $signedInfoId       = "Signature-SignedInfo{$sigNum}";
            $signedPropertiesId = "SignedProperties-Signature{$sigNum}";
            $keyInfoId          = "Certificate{$certNum}-KeyInfo";
            $signatureValueId   = "SignatureValue-Signature{$sigNum}";
            $signatureObjectId  = "SignatureObject{$sigNum}";
            $referenceId        = "Reference-ID-{$sigNum}";

            // ── 4. Cargar documento y calcular digest ──
            $doc = new DOMDocument('1.0', 'UTF-8');
            $doc->preserveWhiteSpace = true;
            $doc->formatOutput = false;
            $doc->loadXML($xmlPuro);

            $docDigest = base64_encode(sha1($doc->C14N(false, false), true));

            // ── 5. Construir SignedProperties (con DataObjectFormat) ──
            $signingTime = date('Y-m-d\TH:i:sP');

            $signedPropertiesXml = '<xades:SignedProperties Id="' . $signedPropertiesId . '">'
                . '<xades:SignedSignatureProperties>'
                . '<xades:SigningTime>' . $signingTime . '</xades:SigningTime>'
                . '<xades:SigningCertificate>'
                . '<xades:Cert>'
                . '<xades:CertDigest>'
                . '<ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"></ds:DigestMethod>'
                . '<ds:DigestValue>' . $certDigestValue . '</ds:DigestValue>'
                . '</xades:CertDigest>'
                . '<xades:IssuerSerial>'
                . '<ds:X509IssuerName>' . $issuerDN . '</ds:X509IssuerName>'
                . '<ds:X509SerialNumber>' . $serialNumber . '</ds:X509SerialNumber>'
                . '</xades:IssuerSerial>'
                . '</xades:Cert>'
                . '</xades:SigningCertificate>'
                . '</xades:SignedSignatureProperties>'
                . '<xades:SignedDataObjectProperties>'
                . '<xades:DataObjectFormat ObjectReference="#' . $referenceId . '">'
                . '<xades:Description>contenido comprobante</xades:Description>'
                . '<xades:MimeType>text/xml</xades:MimeType>'
                . '</xades:DataObjectFormat>'
                . '</xades:SignedDataObjectProperties>'
                . '</xades:SignedProperties>';

            // Digest de SignedProperties (canonicalizado con namespaces)
            $spDoc = new DOMDocument('1.0', 'UTF-8');
            $spXmlWithNs = '<xades:SignedProperties xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Id="' . $signedPropertiesId . '">'
                . substr($signedPropertiesXml, strpos($signedPropertiesXml, '>') + 1);
            $spDoc->loadXML($spXmlWithNs);
            $signedPropsDigest = base64_encode(sha1($spDoc->C14N(false, false), true));

            // ── 6. Construir KeyInfo (con RSAKeyValue) ──
            $keyInfoXml = '<ds:KeyInfo Id="' . $keyInfoId . '">' . "\n"
                . '<ds:X509Data>' . "\n"
                . '<ds:X509Certificate>' . "\n"
                . $certFormatted
                . '</ds:X509Certificate>' . "\n"
                . '</ds:X509Data>' . "\n"
                . '<ds:KeyValue>' . "\n"
                . '<ds:RSAKeyValue>' . "\n"
                . '<ds:Modulus>' . "\n"
                . $modulus . "\n"
                . '</ds:Modulus>' . "\n"
                . '<ds:Exponent>' . $exponent . '</ds:Exponent>' . "\n"
                . '</ds:RSAKeyValue>' . "\n"
                . '</ds:KeyValue>' . "\n"
                . '</ds:KeyInfo>';

            // Digest de KeyInfo (canonicalizado)
            $kiDoc = new DOMDocument('1.0', 'UTF-8');
            $kiXmlWithNs = '<ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Id="' . $keyInfoId . '">'
                . substr($keyInfoXml, strpos($keyInfoXml, '>') + 1);
            $kiDoc->loadXML($kiXmlWithNs);
            $keyInfoDigest = base64_encode(sha1($kiDoc->C14N(false, false), true));

            // ── 7. Construir SignedInfo (3 references) ──
            $signedInfoXml = '<ds:SignedInfo Id="' . $signedInfoId . '">'
                . '<ds:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"></ds:CanonicalizationMethod>'
                . '<ds:SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"></ds:SignatureMethod>'
                // Reference al comprobante
                . '<ds:Reference Id="' . $referenceId . '" URI="#comprobante">'
                . '<ds:Transforms>'
                . '<ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"></ds:Transform>'
                . '</ds:Transforms>'
                . '<ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"></ds:DigestMethod>'
                . '<ds:DigestValue>' . $docDigest . '</ds:DigestValue>'
                . '</ds:Reference>'
                // Reference a SignedProperties (sin Transforms, Type corregido)
                . '<ds:Reference Type="http://uri.etsi.org/01903#SignedProperties" URI="#' . $signedPropertiesId . '">'
                . '<ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"></ds:DigestMethod>'
                . '<ds:DigestValue>' . $signedPropsDigest . '</ds:DigestValue>'
                . '</ds:Reference>'
                // Reference a KeyInfo
                . '<ds:Reference URI="#' . $keyInfoId . '">'
                . '<ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"></ds:DigestMethod>'
                . '<ds:DigestValue>' . $keyInfoDigest . '</ds:DigestValue>'
                . '</ds:Reference>'
                . '</ds:SignedInfo>';

            // ── 8. Canonicalizar SignedInfo y firmar ──
            $siDoc = new DOMDocument('1.0', 'UTF-8');
            $siXmlWithNs = '<ds:SignedInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Id="' . $signedInfoId . '">'
                . substr($signedInfoXml, strpos($signedInfoXml, '>') + 1);
            $siDoc->loadXML($siXmlWithNs);
            $canonicalSignedInfo = $siDoc->C14N(false, false);

            $signatureRaw = '';
            if (!openssl_sign($canonicalSignedInfo, $signatureRaw, $privateKeyResource, OPENSSL_ALGO_SHA1)) {
                throw new SriFirmaException('Error al generar la firma digital: ' . openssl_error_string());
            }
            $signatureValue = "\n" . chunk_split(base64_encode($signatureRaw), 76, "\n");

            // ── 9. Ensamblar firma completa ──
            $dsSignature = '<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Id="' . $signatureId . '">' . "\n"
                . $signedInfoXml . "\n"
                . '<ds:SignatureValue Id="' . $signatureValueId . '">' . "\n"
                . $signatureValue
                . '</ds:SignatureValue>' . "\n"
                . $keyInfoXml . "\n"
                . '<ds:Object Id="' . $signatureObjectId . '">'
                . '<xades:QualifyingProperties xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Target="#' . $signatureId . '">'
                . $signedPropertiesXml
                . '</xades:QualifyingProperties>'
                . '</ds:Object>'
                . '</ds:Signature>';

            // ── 10. Insertar firma en el documento ──
            $signedDoc = new DOMDocument('1.0', 'UTF-8');
            $signedDoc->preserveWhiteSpace = true;
            $signedDoc->formatOutput = false;
            $signedDoc->loadXML($xmlPuro);

            $sigFragment = $signedDoc->createDocumentFragment();
            $sigFragment->appendXML($dsSignature);
            $signedDoc->documentElement->appendChild($sigFragment);

            return $signedDoc->saveXML();
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'CADUCADO')) {
                throw $e;
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

        // Verificar si el certificado principal corresponde a la llave privada
        if (!@openssl_x509_check_private_key($publicCert, $privateKey)) {
            Log::warning('El certificado público principal no coincide con la llave privada. Buscando en extracerts...');
            $foundMatch = false;

            if (!empty($certs['extracerts'])) {
                foreach ((array) $certs['extracerts'] as $extraCert) {
                    if (@openssl_x509_check_private_key($extraCert, $privateKey)) {
                        $publicCert = $extraCert;
                        $foundMatch = true;
                        Log::info('Certificado coincidente hallado en extracerts.');
                        break;
                    }
                }
            }

            if (!$foundMatch) {
                Log::warning('Ningún certificado del P12 coincide con la llave privada.');
            }
        }

        $parsed = openssl_x509_parse($publicCert);
        if ($parsed) {
            $validToTime = $parsed['validTo_time_t'] ?? null;
            $subjectName = $parsed['subject']['CN'] ?? ($parsed['subject']['O'] ?? 'desconocido');
            $fechaCaducidad = $validToTime ? date('d/m/Y H:i', $validToTime) : 'desconocida';

            Log::info('Certificado final seleccionado para firma:', [
                'subject' => $subjectName,
                'issuer' => $parsed['issuer']['CN'] ?? ($parsed['issuer']['O'] ?? 'desconocido'),
                'validTo' => $fechaCaducidad,
            ]);

            if ($validToTime && time() > $validToTime) {
                Log::warning('Certificado P12 caducado.', [
                    'subject' => $subjectName,
                    'validTo' => $fechaCaducidad,
                ]);

                if (!config('sri.permitir_certificados_caducados', false)) {
                    throw new SriFirmaException(
                        "El certificado digital (.p12) de '{$subjectName}' se encuentra CADUCADO desde el {$fechaCaducidad}. " .
                        "El SRI rechaza firmas electrónicas vencidas. Por favor utiliza un certificado vigente."
                    );
                }
            }
        }

        return ['pkey' => $privateKey, 'cert' => $publicCert];
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

        if (preg_match('/-----BEGIN (?:RSA |EC )?PRIVATE KEY-----.*?-----END (?:RSA |EC )?PRIVATE KEY-----/s', $pemBundle, $match)) {
            $privateKey = $match[0];
        }

        if (preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $pemBundle, $matches)) {
            $allCerts = $matches[0];
            // Buscar el certificado que realmente corresponde a la llave privada
            if ($privateKey !== null) {
                foreach ($allCerts as $certPem) {
                    if (@openssl_x509_check_private_key($certPem, $privateKey)) {
                        $publicCert = $certPem;
                        $parsed = openssl_x509_parse($certPem);
                        Log::info('Certificado coincidente con llave privada encontrado.', [
                            'subject' => $parsed['subject']['CN'] ?? ($parsed['subject']['O'] ?? 'desconocido'),
                            'issuer' => $parsed['issuer']['CN'] ?? ($parsed['issuer']['O'] ?? 'desconocido'),
                        ]);
                        break;
                    }
                }
            }
            // Fallback al primer certificado si no hubo coincidencia explícita
            if ($publicCert === null && isset($allCerts[0])) {
                $publicCert = $allCerts[0];
                Log::warning('No se halló certificado con openssl_x509_check_private_key. Usando primer certificado.');
            }
        }

        if (!$privateKey || !$publicCert) {
            return null;
        }

        return $this->validarCertificados(['pkey' => $privateKey, 'cert' => $publicCert]);
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
        $xadesNS = 'http://uri.etsi.org/01903/v1.3.2#';
        $dsNS = XMLSecurityDSig::XMLDSIGNS;

        $object = $dom->createElementNS($dsNS, 'ds:Object');
        $qualifyingProperties = $dom->createElementNS($xadesNS, 'xades:QualifyingProperties');
        $qualifyingProperties->setAttribute('Target', '#' . $signatureId);
        $qualifyingProperties->setAttribute('Id', $qualifyingPropsId);

        $signedProps = $dom->createElementNS($xadesNS, 'xades:SignedProperties');
        $signedProps->setAttribute('Id', $signedPropsId);

        $signedSigProps = $dom->createElementNS($xadesNS, 'xades:SignedSignatureProperties');
        $this->appendText($dom, $signedSigProps, 'xades:SigningTime', gmdate('c'), $xadesNS);

        $signingCertificate = $dom->createElementNS($xadesNS, 'xades:SigningCertificate');
        $cert = $dom->createElementNS($xadesNS, 'xades:Cert');
        $certDigest = $dom->createElementNS($xadesNS, 'xades:CertDigest');
        $digestMethod = $dom->createElementNS($dsNS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', XMLSecurityDSig::SHA1);
        $digestValue = $dom->createElementNS($dsNS, 'ds:DigestValue', $this->certDigest($publicCert));
        $certDigest->appendChild($digestMethod);
        $certDigest->appendChild($digestValue);

        $issuerSerial = $dom->createElementNS($xadesNS, 'xades:IssuerSerial');
        $parsed = openssl_x509_parse($publicCert) ?: [];
        $issuerName = $parsed['issuer'] ?? [];
        $issuerText = $this->buildIssuerName($issuerName);
        $serialNumber = $parsed['serialNumber'] ?? '';

        $this->appendText($dom, $issuerSerial, 'ds:X509IssuerName', $issuerText, $dsNS);
        $this->appendText($dom, $issuerSerial, 'ds:X509SerialNumber', $this->normalizeSerialNumber((string) $serialNumber), $dsNS);

        $cert->appendChild($certDigest);
        $cert->appendChild($issuerSerial);
        $signingCertificate->appendChild($cert);
        $signedSigProps->appendChild($signingCertificate);
        $signedProps->appendChild($signedSigProps);

        $signedDataObjectProps = $dom->createElementNS($xadesNS, 'xades:SignedDataObjectProperties');
        $signedProps->appendChild($signedDataObjectProps);

        $qualifyingProperties->appendChild($signedProps);
        $object->appendChild($qualifyingProperties);

        return [
            'object' => $object,
            'signedProps' => $signedProps,
        ];
    }

    private function appendText(DOMDocument $dom, $parent, string $name, string $value, ?string $ns = null): void
    {
        $node = $ns ? $dom->createElementNS($ns, $name) : $dom->createElement($name);
        $node->appendChild($dom->createTextNode($value));
        $parent->appendChild($node);
    }


    private function certDigest(string $pem): string
    {
        $der = $this->pemToDer($pem);

        return base64_encode(hash('sha1', $der, true));
    }

    private function pemToDer(string $pem): string
    {
        $clean = preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $pem);

        return base64_decode($clean) ?: '';
    }

    private function buildIssuerName(array $issuerMap): string
    {
        $issuer = [];
        
        // El validador del SRI (Java) espera el formato RFC2253 estricto.
        // Esto implica invertir el orden del arreglo devuelto por openssl.
        $keys = array_reverse(array_keys($issuerMap));
        
        foreach ($keys as $key) {
            $value = $issuerMap[$key];
            if (is_array($value)) {
                $value = $value[0];
            }
            
            // Java X500Principal formatea OIDs desconocidos o específicos (como organizationIdentifier)
            // usando su OID numérico y el valor codificado en ASN.1 (DER) en formato hexadecimal precedido por '#'.
            if ($key === 'organizationIdentifier' || $key === '2.5.4.97') {
                $key = '2.5.4.97';
                // Codificamos como UTF8String (Tag 0x0C).
                // sprintf('%02x', strlen) funciona para valores de hasta 127 bytes.
                $hex = '0c' . sprintf('%02x', strlen($value)) . bin2hex($value);
                $value = '#' . $hex;
            }
            
            $issuer[] = $key . '=' . $value;
        }
        
        return implode(',', $issuer);
    }


    private function normalizeX509SerialNumbers(\DOMNode $node): void
    {
        $xpath = new \DOMXPath($node->ownerDocument ?? $node);
        foreach ($xpath->query('.//*[local-name()="X509SerialNumber"]', $node) as $serialNode) {
            $serialNode->nodeValue = $this->normalizeSerialNumber($serialNode->textContent);
        }
    }

    private function firstNodeByName(\DOMNode $node, string $localName): ?\DOMNode
    {
        $xpath = new \DOMXPath($node->ownerDocument ?? $node);
        $query = './/*[local-name()="' . $localName . '"]';
        $found = $xpath->query($query, $node);

        return $found?->item(0) ?: null;
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
