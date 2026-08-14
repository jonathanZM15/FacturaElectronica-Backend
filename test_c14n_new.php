<?php
$xml = '<?xml version="1.0" encoding="UTF-8"?>
<factura id="comprobante" version="2.1.0"><infoTributaria><ambiente>1</ambiente><tipoEmision>1</tipoEmision><razonSocial>ALMEIDA ZAMBRANO EDISON ERNESTO</razonSocial><nombreComercial>ALMEIDA ZAMBRANO EDISON ERNESTO</nombreComercial><ruc>1310675341001</ruc><claveAcceso>1208202601131067534100120026060000000210000002117</claveAcceso><codDoc>01</codDoc><estab>002</estab><ptoEmi>606</ptoEmi><secuencial>000000021</secuencial><dirMatriz>MANABI / CHONE / CHONE / AV. ELOY ALFARO S/N Y SN</dirMatriz></infoTributaria><infoFactura><fechaEmision>12/08/2026</fechaEmision><dirEstablecimiento>MANABI / CHONE / CHONE / AV. ELOY ALFARO S/N Y SN</dirEstablecimiento><obligadoContabilidad>NO</obligadoContabilidad><tipoIdentificacionComprador>07</tipoIdentificacionComprador><razonSocialComprador>CONSUMIDOR FINAL</razonSocialComprador><identificacionComprador>9999999999999</identificacionComprador><direccionComprador>Ecuador</direccionComprador><totalSinImpuestos>100.00</totalSinImpuestos><totalDescuento>0.00</totalDescuento><totalConImpuestos><totalImpuesto><codigo>2</codigo><codigoPorcentaje>4</codigoPorcentaje><baseImponible>100.00</baseImponible><tarifa>15.00</tarifa><valor>15.00</valor></totalImpuesto></totalConImpuestos><propina>0.00</propina><importeTotal>115.00</importeTotal><moneda>DOLAR</moneda><pagos><pago><formaPago>01</formaPago><total>115.00</total></pago></pagos></infoFactura><detalles><detalle><descripcion>Licencia de Software Anual</descripcion><cantidad>1.000000</cantidad><precioUnitario>100.000000</precioUnitario><descuento>0.00</descuento><precioTotalSinImpuesto>100.00</precioTotalSinImpuesto><impuestos><impuesto><codigo>2</codigo><codigoPorcentaje>4</codigoPorcentaje><tarifa>15.00</tarifa><baseImponible>100.00</baseImponible><valor>15.00</valor></impuesto></impuestos></detalle></detalles><infoAdicional><campoAdicional nombre="Email">cliente@email.com</campoAdicional></infoAdicional><ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Id="Signature498108">
<ds:SignedInfo Id="Signature-SignedInfo498108"><ds:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/><ds:SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"/><ds:Reference Id="Reference-ID-498108" URI="#comprobante"><ds:Transforms><ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/></ds:Transforms><ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/><ds:DigestValue>vCojeMTlWbSmicO8r/8b6w9qLsI=</ds:DigestValue></ds:Reference><ds:Reference Type="http://uri.etsi.org/01903#SignedProperties" URI="#SignedProperties-Signature498108"><ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/><ds:DigestValue>HpHxDHa/oZaIpkfn1AGr3Qx5TPY=</ds:DigestValue></ds:Reference><ds:Reference URI="#Certificate815435-KeyInfo"><ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/><ds:DigestValue>etAM6QdoFkTIreDjA6SPsPqdPsw=</ds:DigestValue></ds:Reference></ds:SignedInfo>
<ds:SignatureValue Id="SignatureValue-Signature498108">
dXsw1PTrRjdi6o6lR1bHx8GBtOXdUtLyVpldBgB8lXKkOiWa8rMR7q5pIj2m8A+rUxMkcjLcp+cw
+MUOi1ED6nWN9vCqwDFtXGIHc4SFp9JPrtGn2hRqUkZvI+sH9mIq753+8DohYls19ym7Y9Y7cEtb
Myod3bV+LyyJp+SY4QcjGn1ocBC2XDxq6pALb55D3TZfSjZo7Rgr2x7WziIhKrf1D/+o+ZJFjJr4
OxreDWDBn6wJX7HMtWibGm/e+ebxqaL0cDU+vuWzJGf2dCVvmxuYeL91pYewcVzwilqHijYJy3BR
heDwXTaHAQ4Cak7CRdsmIzepR5c2ZxDgbUmukA==
</ds:SignatureValue>
<ds:KeyInfo Id="Certificate815435-KeyInfo">
<ds:X509Data>
<ds:X509Certificate>
MIIJZjCCB06gAwIBAgIINwWEx4xFrSEwDQYJKoZIhvcNAQELBQAwgbgxCzAJBgNVBAYTAkVTMUQw
QgYDVQQHDDtCYXJjZWxvbmEgKHNlZSBjdXJyZW50IGFkZHJlc3MgYXQgd3d3LnVhbmF0YWNhLmNv
bS9hZGRyZXNzKTEWMBQGA1UECgwNVUFOQVRBQ0EgUy5BLjEVMBMGA1UECwwMVFNQLVVBTkFUQUNB
MRowGAYDVQQDDBFVQU5BVEFDQSBDQTIgMjAxNjEYMBYGA1UEYQwPVkFURVMtQTY2NzIxNDk5MB4X
DTI1MDQwNDE2NTcwMFoXDTI3MDQwNDE2NTcwMFowgaQxCzAJBgNVBAYTAkVDMRkwFwYDVQQEDBBB
TE1FSURBIFpBTUJSQU5PMRcwFQYDVQQqDA5FRElTT04gRVJORVNUTzEZMBcGA1UEBRMQSURDRUMt
MTMxMDY3NTM0MTEoMCYGA1UEAwwfRURJU09OIEVSTkVTVE8gQUxNRUlEQSBaQU1CUkFOTzEcMBoG
A1UEYQwTVElORUMtMTMxMDY3NTM0MTAwMTCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEB
AL275qwR3y9AFQxEVXDqQCCLT2XON1UxGTN/l2PJaEY6+XmIxQP1PEFe5JzfYAvZAajKD1W/Hmnv
EttM62Vfhm730mXCesmH3kV++tqIKV13tQ1S26sX0GQOAfO3zZOTP1BXTHEimWjk7u3v5+05fUzG
YPuOA1b4GVgbz8i3e80bpMv8bY8hg9uG1BndCKNWshbS10DJSJ7DHGr02xnn+Eef6FPluP443UaZ
+QN+QAwytvj/yYkoxplO6Tjn3nCC/hcrzhMnoX/w1HRuz7IoCBp1c5KUbmOVSftCRReerTYKhiFk
boiNnNiCg2N0rLFdoxMXr3z8SYpXRvre9yw9zv0CAwEAAaOCBIQwggSAMBsGCysGAQQBgvE2ZgMB
BAwMCjEzMTA2NzUzNDEwHgYLKwYBBAGC8TZmAwsEDwwNMTMxMDY3NTM0MTAwMTCB1wYIKwYBBQUH
AQEEgcowgccwVQYIKwYBBQUHMAKGSWh0dHA6Ly93d3cudWFuYXRhY2EuY29tL3B1YmxpYy9kb3du
bG9hZC90c3BfY2VydGlmaWNhdGVzL3N1Ym9yZGluYXRlMS5jcnQwNgYIKwYBBQUHMAGGKmh0dHA6
Ly9vY3NwMS51YW5hdGFjYS5jb20vcHVibGljL3BraS9vY3NwLzA2BggrBgEFBQcwAYYqaHR0cDov
L29jc3AyLnVhbmF0YWNhLmNvbS9wdWJsaWMvcGtpL29jc3AvMB0GA1UdDgQWBBSb8flp7lCOR/Di
xvZzqUyQ+TDGDDAMBgNVHRMBAf8EAjAAMB8GA1UdIwQYMBaAFH1X52BzzgdGqeKjaPDhEbJ0knP9
MIGZBgNVHSAEgZEwgY4wgYsGDCsGAQQBgvE2ZgIBATB7MDcGCCsGAQUFBwIBFitodHRwczovL3d3
dy51YW5hdGFjYS5jb20vcHVibGljL3BraS9kcGMtZWMvMEAGCCsGAQUFBwICMDQMMkNFUlRJRklD
QURPIERFIFBFUlNPTkEgTkFUVVJBTCBPIEZJU0lDQSBFTiBBUkNISVZPMIGPBgNVHR8EgYcwgYQw
QKA+oDyGOmh0dHA6Ly9jcmwxLnVhbmF0YWNhLmNvbS9wdWJsaWMvcGtpL2NybC9DQTJzdWJvcmRp
bmFkYS5jcmwwQKA+oDyGOmh0dHA6Ly9jcmwyLnVhbmF0YWNhLmNvbS9wdWJsaWMvcGtpL2NybC9D
QTJzdWJvcmRpbmFkYS5jcmwwDgYDVR0PAQH/BAQDAgXgMB0GA1UdJQQWMBQGCCsGAQUFBwMCBggr
BgEFBQcDBDCCAboGA1UdEQSCAbEwggGtgRNkbnRlc2l0ZWNAZ21haWwuY29toBsGCysGAQQBgvE2
ZgMBoAwMCjEzMTA2NzUzNDGgHwYLKwYBBAGC8TZmAwKgEAwORURJU09OIEVSTkVTVE+gGAYLKwYB
BAGC8TZmAwOgCQwHQUxNRUlEQaAZBgsrBgEEAYLxNmYDBKAKDAhaQU1CUkFOT6BwBgsrBgEEAYLx
NmYDB6BhDF9DQUxMRTogIEFWLiBFTE9ZIEFMRkFSTyAgTk1FUk86ICBTIE4gIFJFRkVSRU5DSUE6
ICBBIFRSRUlOVEEgTUVUUk9TIERFTCBDT0xFR0lPIFJBWU1VTkRPIEFWRUlHQaAfBgsrBgEEAYLx
NmYDCKAQDA4wMDU5Mzk4Nzk4MzY2MaAWBgsrBgEEAYLxNmYDCaAHDAVDSE9ORaAeBgsrBgEEAYLx
NmYDC6APDA0xMzEwNjc1MzQxMDAxoBMGCysGAQQBgvE2ZgMMoAQMAkVDoCkGCysGAQQBgvE2ZgMy
oBoMGFBFUlNPTkEgTkFUVVJBTCBPIEZJU0lDQaAYBgsrBgEEAYLxNmYDM6AJDAdBUkNISVZPMA0G
CSqGSIb3DQEBCwUAA4ICAQBunrt+D3WVIo81iiyz/Ziq22GJUId5r8A+Ic2qK1lU8mjSyqpafyij
9oEqkg+9fXpf8r2jK6+3N5x7S7sFvtevffDP9Nlo3vgSfi5Vk4wsWipaZzkuki0bt7qOu5y4+GkO
legsxRso86yCj5vmne5bjsHdoyLa92YPmHIUmBLQQbe+WErjKq40RFGU7J0woQjyV7mA9lWphKQA
cLw3pIMEYWoLh+YVAVvs9M/4sWZoGx4Nh2YInTQCpOvTSVg9Z6oqP/z9U9NOuX49GcL8QeY/F9tK
f/EBj1dsWr7fQ5kSzxm4hAGxs/p58GOoFS+E/Ny+YG8kqogSoedh44Z9K6o2HRF86pAy2JZH/Tmf
+bqqKjrhm6hhsHTJ9AFlr/UB+BSyN9ZVQ6bQbtm3xoemV01XInYhYOfjt64gSThA4xW4ioKU77u7
o8yEwQ2yR9nHYU4b2/TPaKc60pTC3vqxRM/fU460TBGABx3CxuutSta+1h8VCKo9PBJ0Gu0jhZSm
y93E7z8wHOUEc9VDzf6glhpuheAsUu+OoGQZoX4M76Sif0YunpwqxhBDqgAt/7Wc6ZB6tWsJUWF9
GSalZ7QqCCqx9q97yzmqmg23DOi3ytLmsg+4mYASOeGfOXCBPhSoC1U6bYeQiOohSICPwzcyrRZl
L30d88rSZNU6ev4NGIiZDQ==
</ds:X509Certificate>
</ds:X509Data>
<ds:KeyValue>
<ds:RSAKeyValue>
<ds:Modulus>
vbvmrBHfL0AVDERVcOpAIItPZc43VTEZM3+XY8loRjr5eYjFA/U8QV7knN9gC9kBqMoPVb8eae8S
20zrZV+GbvfSZcJ6yYfeRX762ogpXXe1DVLbqxfQZA4B87fNk5M/UFdMcSKZaOTu7e/n7Tl9TMZg
+44DVvgZWBvPyLd7zRuky/xtjyGD24bUGd0Io1ayFtLXQMlInsMcavTbGef4R5/oU+W4/jjdRpn5
A35ADDK2+P/JiSjGmU7pOOfecIL+FyvOEyehf/DUdG7PsigIGnVzkpRuY5VJ+0JFF56tNgqGIWRu
iI2c2IKDY3SssV2jExevfPxJildG+t73LD3O/Q==
</ds:Modulus>
<ds:Exponent>AQAB</ds:Exponent>
</ds:RSAKeyValue>
</ds:KeyValue>
</ds:KeyInfo>
<ds:Object Id="SignatureObject498108"><xades:QualifyingProperties xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Target="#Signature498108"><xades:SignedProperties Id="SignedProperties-Signature498108"><xades:SignedSignatureProperties><xades:SigningTime>2026-08-12T23:52:21-05:00</xades:SigningTime><xades:SigningCertificate><xades:Cert><xades:CertDigest><ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/><ds:DigestValue>hrcXXGlKiZ5moh90mnBuWSRDxjk=</ds:DigestValue></xades:CertDigest><xades:IssuerSerial><ds:X509IssuerName>organizationIdentifier=VATES-A66721499,CN=UANATACA CA2 2016,OU=TSP-UANATACA,O=UANATACA S.A.,L=Barcelona (see current address at www.uanataca.com/address),C=ES</ds:X509IssuerName><ds:X509SerialNumber>3964721039556324641</ds:X509SerialNumber></xades:IssuerSerial></xades:Cert></xades:SigningCertificate></xades:SignedSignatureProperties><xades:SignedDataObjectProperties><xades:DataObjectFormat ObjectReference="#Reference-ID-498108"><xades:Description>contenido comprobante</xades:Description><xades:MimeType>text/xml</xades:MimeType></xades:DataObjectFormat></xades:SignedDataObjectProperties></xades:SignedProperties></xades:QualifyingProperties></ds:Object></ds:Signature></factura>';

$doc = new DOMDocument();
$doc->loadXML($xml);

$xpath = new DOMXPath($doc);
$xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
$xpath->registerNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');

$siNode = $xpath->query('//ds:SignedInfo')->item(0);
$siC14n = $siNode->C14N(false, false);
echo "SignedInfo C14N:\n";
echo "================\n";
echo $siC14n . "\n";
echo "================\n";

$kiNode = $xpath->query('//ds:KeyInfo')->item(0);
$kiC14n = $kiNode->C14N(false, false);
echo "KeyInfo C14N:\n";
echo "================\n";
echo $kiC14n . "\n";
echo "================\n";

$certNode = $xpath->query('//ds:X509Certificate')->item(0);
$certPem = "-----BEGIN CERTIFICATE-----\n" . trim($certNode->textContent) . "\n-----END CERTIFICATE-----";
$publicKey = openssl_pkey_get_public($certPem);
$sigValueNode = $xpath->query('//ds:SignatureValue')->item(0);
$sigValue = base64_decode(trim($sigValueNode->textContent));
$isValid = openssl_verify($siC14n, $sigValue, $publicKey, OPENSSL_ALGO_SHA1);
echo "RSA Signature Valid: " . ($isValid === 1 ? 'YES' : 'NO') . "\n";
