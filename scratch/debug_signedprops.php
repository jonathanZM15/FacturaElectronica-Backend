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

$dom = new DOMDocument('1.0', 'UTF-8');
$dom->loadXML($xmlPuro);
$signatureId = 'Signature-test';
$signedPropsId = 'SignedProperties-test';
$qualifyingPropsId = 'QualifyingProperties-test';
$publicCert = $certs['cert'];

$dsig = new XMLSecurityDSig();
$dsig->setCanonicalMethod(XMLSecurityDSig::C14N);
$dsig->sigNode->setAttribute('Id', $signatureId);

$xades = (new ReflectionClass($sigService))->getMethod('buildXadesObject')->invoke($sigService, $dsig->sigNode->ownerDocument, $signatureId, $signedPropsId, $qualifyingPropsId, $publicCert);
$dsig->sigNode->appendChild($xades['object']);

// What is the C14N of $xades['signedProps'] AT THIS MOMENT?
echo "--- C14N of SignedProperties DURING addReference ---\n";
echo $xades['signedProps']->C14N(false, false);
echo "\n----------------------------------------------------\n";

$dsig->appendSignature($dom->documentElement);

echo "--- C14N of SignedProperties AFTER appendSignature ---\n";
echo $xades['signedProps']->C14N(false, false);
echo "\n----------------------------------------------------\n";
