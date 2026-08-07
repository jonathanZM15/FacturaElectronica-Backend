<?php
$xml = file_get_contents('scratch/c14nafter.xml');
$dom = new DOMDocument();
$dom->loadXML($xml);

$xpath = new DOMXPath($dom);
$xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
$signedInfo = $xpath->query('//ds:SignedInfo')->item(0);

echo $signedInfo->C14N(false, false);
