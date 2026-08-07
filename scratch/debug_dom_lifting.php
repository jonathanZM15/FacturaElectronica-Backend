<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

$dom = new DOMDocument('1.0', 'UTF-8');
$dom->loadXML('<factura id="comprobante"></factura>');

$dsig = new XMLSecurityDSig();
$dsig->setCanonicalMethod(XMLSecurityDSig::C14N);
$dsig->sigNode->setAttribute('Id', 'test');
$dsig->addReference($dom->documentElement, XMLSecurityDSig::SHA256, ['http://www.w3.org/2000/09/xmldsig#enveloped-signature']);

// Add QualifyingProperties with xades namespace
$object = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Object');
$qp = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:QualifyingProperties');
$object->appendChild($qp);
$dsig->sigNode->appendChild($object);

// What is the C14N of SignedInfo BEFORE saveXML?
$xpath = new DOMXPath($dsig->sigNode->ownerDocument);
$xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
$signedInfo = $xpath->query('//ds:SignedInfo', $dsig->sigNode)->item(0);

echo "C14N of SignedInfo BEFORE saveXML:\n";
echo $signedInfo->C14N(false, false) . "\n\n";

$dom->documentElement->appendChild($dsig->sigNode);
$xml = $dom->saveXML();

// Reload
$dom2 = new DOMDocument();
$dom2->loadXML($xml);
$xpath2 = new DOMXPath($dom2);
$xpath2->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
$signedInfo2 = $xpath2->query('//ds:SignedInfo')->item(0);

echo "C14N of SignedInfo AFTER saveXML:\n";
echo $signedInfo2->C14N(false, false) . "\n\n";

