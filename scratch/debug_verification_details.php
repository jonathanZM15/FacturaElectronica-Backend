<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

$xmlPuro = '<?xml version="1.0" encoding="UTF-8"?><factura id="comprobante" version="2.1.0"><infoTributaria><ambiente>1</ambiente><tipoEmision>1</tipoEmision><razonSocial>ALMEIDA ZAMBRANO EDISON ERNESTO</razonSocial><nombreComercial>ALMEIDA ZAMBRANO EDISON ERNESTO</nombreComercial><ruc>1310675341001</ruc><claveAcceso>3107202601131067534100120026060000000110000001112</claveAcceso><codDoc>01</codDoc><estab>002</estab><ptoEmi>606</ptoEmi><secuencial>000000011</secuencial><dirMatriz>MANABI / CHONE / CHONE / AV. ELOY ALFARO S/N Y SN</dirMatriz></infoTributaria></factura>';

$p12Files = glob('storage/app/private/sri/certificados/*.p12');
$sigService = new \App\Services\SriSignatureService();
$certs = (new ReflectionClass($sigService))->getMethod('abrirPkcs12DesdeArchivo')->invoke($sigService, $p12Files[0], 'Prueba123');

$signedXml = $sigService->firmarXml($xmlPuro, $p12Files[0], 'Prueba123');

$domCheck = new DOMDocument();
$domCheck->loadXML($signedXml);

$dsigCheck = new XMLSecurityDSig();
$dsigCheck->idKeys = ['id', 'Id', 'ID'];
$sigNode = $dsigCheck->locateSignature($domCheck);
$dsigCheck->canonicalizeSignedInfo();

$refs = (new DOMXPath($domCheck))->query('//*[local-name()="Reference"]');
$ref0 = $refs->item(0);

// Let's manually do processTransforms
$node = (new DOMXPath($domCheck))->query('//*[@id="comprobante"]')->item(0);
$c14nAfter = $dsigCheck->processTransforms($ref0, $node, false);
$computedDigest = base64_encode(hash('sha256', $c14nAfter, true));
$expectedDigest = (new DOMXPath($domCheck))->evaluate('string(./*[local-name()="DigestValue"])', $ref0);

echo "Computed Digest: " . $computedDigest . "\n";
echo "Expected Digest: " . $expectedDigest . "\n";

echo "C14nAfter length: " . strlen($c14nAfter) . "\n";

file_put_contents('scratch/c14nafter.xml', $c14nAfter);
