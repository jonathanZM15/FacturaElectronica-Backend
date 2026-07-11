<?php

namespace Tests\Unit;

use App\Services\SriClaveAccesoService;
use PHPUnit\Framework\TestCase;

class SriClaveAccesoServiceTest extends TestCase
{
    public function test_it_generates_a_49_digit_access_key(): void
    {
        $service = new SriClaveAccesoService();

        $clave = $service->generarFactura(
            '08072026',
            '1790011111001',
            'PRUEBAS',
            '001002',
            '000000123',
            '12345678',
            '01',
            '1'
        );

        $this->assertMatchesRegularExpression('/^\d{49}$/', $clave);
        $this->assertSame('080720260117900111110011001002000000123123456781', substr($clave, 0, 48));
        $this->assertSame(
            $service->digitoVerificadorModulo11(substr($clave, 0, 48)),
            substr($clave, -1)
        );
    }
}