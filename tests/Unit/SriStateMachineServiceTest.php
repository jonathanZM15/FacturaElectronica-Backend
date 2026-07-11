<?php

namespace Tests\Unit;

use App\Services\SriStateMachineService;
use PHPUnit\Framework\TestCase;

class SriStateMachineServiceTest extends TestCase
{
    public function test_it_allows_expected_transitions(): void
    {
        $service = new SriStateMachineService();

        $service->assertTransition('BORRADOR', 'PROCESANDO');
        $service->assertTransition('PROCESANDO', 'FIRMADO');
        $service->assertTransition('FIRMADO', 'ENVIADO');
        $service->assertTransition('ENVIADO', 'RECIBIDA');
        $service->assertTransition('RECIBIDA', 'AUTORIZADO');

        $this->assertTrue(true);
    }

    public function test_it_rejects_invalid_transitions(): void
    {
        $this->expectException(\RuntimeException::class);

        (new SriStateMachineService())->assertTransition('AUTORIZADO', 'ENVIADO');
    }
}