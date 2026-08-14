<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\SriSignatureService;

$service = new SriSignatureService();

$xml = '<?xml version="1.0" encoding="UTF-8"?><factura id="comprobante" version="2.1.0"><infoTributaria><ambiente>1</ambiente></infoTributaria></factura>';

$certPath = __DIR__ . '/storage/app/private/sri/certificados/cert_6a7c808da876a5.62485370.p12';
$certPass = 'Edison1993*';

try {
    // Call the original method to sign
    $signedXml = $service->firmarXml($xml, $certPath, $certPass);
    // echo "SIGNED XML:\n$signedXml\n";

    // Parse back the generated signature
    $doc = new DOMDocument();
    $doc->loadXML($signedXml);

    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
    $xpath->registerNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');

    // 1. Verify SignedInfo RSA signature
    $siNode = $xpath->query('//ds:SignedInfo')->item(0);
    $siC14n = $siNode->C14N(false, false);
    
    $certNode = $xpath->query('//ds:X509Certificate')->item(0);
    $certPem = "-----BEGIN CERTIFICATE-----\n" . trim($certNode->textContent) . "\n-----END CERTIFICATE-----";
    
    $sigValueNode = $xpath->query('//ds:SignatureValue')->item(0);
    $sigValue = base64_decode(trim($sigValueNode->textContent));
    
    $publicKey = openssl_pkey_get_public($certPem);
    $isValid = openssl_verify($siC14n, $sigValue, $publicKey, OPENSSL_ALGO_SHA1);
    
    if ($isValid === 1) {
        echo "PASS: RSA Signature is valid!\n";
    } else {
        echo "FAIL: RSA Signature is INVALID!\n";
    }

    // 2. Verify KeyInfo digest match
    $kiNode = $xpath->query('//ds:KeyInfo')->item(0);
    $kiC14n = $kiNode->C14N(false, false);
    $kiHash = base64_encode(sha1($kiC14n, true));
    
    $expectedKiHashNode = $xpath->query('//ds:Reference[contains(@URI, "KeyInfo")]/ds:DigestValue')->item(0);
    $expectedKiHash = $expectedKiHashNode->textContent;

    if ($kiHash === $expectedKiHash) {
        echo "PASS: KeyInfo hash matches!\n";
    } else {
        echo "FAIL: KeyInfo hash mismatch. Expected $expectedKiHash, got $kiHash\n";
    }

    // 3. Verify SignedProperties digest match
    $spNode = $xpath->query('//xades:SignedProperties')->item(0);
    $spC14n = $spNode->C14N(false, false);
    $spHash = base64_encode(sha1($spC14n, true));

    $expectedSpHashNode = $xpath->query('//ds:Reference[contains(@URI, "SignedProperties")]/ds:DigestValue')->item(0);
    $expectedSpHash = $expectedSpHashNode->textContent;

    if ($spHash === $expectedSpHash) {
        echo "PASS: SignedProperties hash matches!\n";
    } else {
        echo "FAIL: SignedProperties hash mismatch. Expected $expectedSpHash, got $spHash\n";
    }

    // 4. Verify Comprobante digest match
    $sigNode = $xpath->query('//ds:Signature')->item(0);
    if ($sigNode) {
        $sigNode->parentNode->removeChild($sigNode);
    }
    $docC14n = $doc->documentElement->C14N(false, false);
    $docHash = base64_encode(sha1($docC14n, true));
    
    $expectedDocHashNode = $xpath->query('//ds:Reference[contains(@URI, "comprobante")]/ds:DigestValue')->item(0);
    $expectedDocHash = $expectedDocHashNode->textContent;
    
    if ($docHash === $expectedDocHash) {
        echo "PASS: Comprobante hash matches!\n";
    } else {
        echo "FAIL: Comprobante hash mismatch. Expected $expectedDocHash, got $docHash\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
