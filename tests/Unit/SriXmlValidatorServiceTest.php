<?php

namespace Tests\Unit;

use App\Services\SriXmlValidatorService;
use PHPUnit\Framework\TestCase;

class SriXmlValidatorServiceTest extends TestCase
{
    public function test_it_accepts_a_minimal_factura_xml(): void
    {
        $validator = new SriXmlValidatorService();
        $xml = '<?xml version="1.0" encoding="UTF-8"?><factura><infoTributaria><claveAcceso>1234567890123456789012345678901234567890123456789</claveAcceso><ruc>1790011111001</ruc></infoTributaria><infoFactura><fechaEmision>08/07/2026</fechaEmision></infoFactura><detalles><detalle /></detalles></factura>';

        $result = $validator->validateFactura($xml);

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['errors']);
    }

    public function test_it_rejects_an_invalid_xml(): void
    {
        $validator = new SriXmlValidatorService();
        $result = $validator->validateFactura('<notaCredito></notaCredito>');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }
}