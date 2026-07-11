<?php

namespace Tests\Unit;

use App\Services\SriResponseParserService;
use PHPUnit\Framework\TestCase;

class SriResponseParserServiceTest extends TestCase
{
    public function test_it_parses_recepcion_success_response(): void
    {
        $parser = new SriResponseParserService();
        $response = (object) [
            'RespuestaRecepcionComprobante' => (object) [
                'estado' => 'RECIBIDA',
                'comprobantes' => (object) [
                    'comprobante' => (object) [
                        'mensajes' => (object) [
                            'mensaje' => (object) [
                                'identificador' => '45',
                                'mensaje' => 'OK',
                                'informacionAdicional' => 'Recibido',
                                'tipo' => 'INFO',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $parsed = $parser->parseRecepcion($response);

        $this->assertTrue($parsed['success']);
        $this->assertSame('RECIBIDA', $parsed['estado']);
        $this->assertSame('45', $parsed['errores'][0]['identificador']);
    }

    public function test_it_parses_autorizacion_success_response(): void
    {
        $parser = new SriResponseParserService();
        $response = (object) [
            'RespuestaAutorizacionComprobante' => (object) [
                'autorizaciones' => (object) [
                    'autorizacion' => (object) [
                        'estado' => 'AUTORIZADO',
                        'numeroAutorizacion' => '1234567890',
                        'fechaAutorizacion' => '2026-07-08T10:00:00-05:00',
                        'comprobante' => '<xml />',
                    ],
                ],
            ],
        ];

        $parsed = $parser->parseAutorizacion($response);

        $this->assertTrue($parsed['success']);
        $this->assertSame('AUTORIZADO', $parsed['estado']);
        $this->assertSame('1234567890', $parsed['numero_autorizacion']);
        $this->assertSame('<xml />', $parsed['xml_autorizado']);
    }
}