<?php

namespace Tests\Unit;

use App\Models\Cliente;
use App\Models\Company;
use App\Models\Comprobante;
use App\Models\ComprobanteDetalle;
use App\Models\ComprobanteImpuesto;
use App\Models\Establecimiento;
use App\Models\TipoImpuesto;
use App\Services\SriXmlGeneratorService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SriXmlGeneratorServiceTest extends TestCase
{
    public function test_total_con_impuestos_uses_summary_rows_without_duplicating_detail_taxes(): void
    {
        $tipoIva = new TipoImpuesto([
            'codigo_impuesto' => 2,
            'codigo_porcentaje' => 4,
            'valor_tarifa' => 15,
        ]);

        $detalleImpuesto = new ComprobanteImpuesto([
            'comprobante_detalle_id' => 10,
            'tipo_impuesto_id' => 1,
            'base_imponible' => 100,
            'tarifa' => 15,
            'valor' => 15,
        ]);
        $detalleImpuesto->setRelation('tipoImpuesto', $tipoIva);

        $resumenImpuesto = new ComprobanteImpuesto([
            'comprobante_detalle_id' => null,
            'tipo_impuesto_id' => 1,
            'base_imponible' => 100,
            'tarifa' => 15,
            'valor' => 15,
        ]);
        $resumenImpuesto->setRelation('tipoImpuesto', $tipoIva);

        $detalle = new ComprobanteDetalle([
            'id' => 10,
            'descripcion' => 'Licencia de Software Anual',
            'cantidad' => 1,
            'precio_unitario' => 100,
            'descuento' => 0,
            'subtotal' => 100,
        ]);
        $detalle->setRelation('impuestos', new Collection([$detalleImpuesto]));

        $comprobante = new Comprobante([
            'emisor_id' => 1,
            'establecimiento_id' => 1,
            'cliente_id' => 1,
            'secuencial' => 28,
            'codigo_establecimiento' => '001',
            'punto_emision_codigo' => '001',
            'fecha_emision' => '2026-07-12',
            'subtotal_sin_impuestos' => 100,
            'total_descuento' => 0,
            'total' => 115,
            'ambiente' => 'PRUEBAS',
        ]);

        $comprobante->setRelation('company', new Company([
            'ruc' => '1712345675001',
            'razon_social' => 'Movil',
            'nombre_comercial' => 'Movil',
            'direccion_matriz' => 'Manta',
            'obligado_contabilidad' => 'SI',
        ]));
        $comprobante->setRelation('cliente', new Cliente([
            'tipo_identificacion' => 'CONSUMIDOR_FINAL',
            'identificacion' => '9999999999999',
            'razon_social' => 'CONSUMIDOR FINAL',
            'direccion' => 'Ecuador',
            'email' => 'cliente@email.com',
        ]));
        $comprobante->setRelation('establecimiento', new Establecimiento([
            'direccion' => 'Direccion Matriz',
        ]));
        $comprobante->setRelation('detalles', new Collection([$detalle]));
        $comprobante->setRelation('impuestos', new Collection([$detalleImpuesto, $resumenImpuesto]));

        $xml = (new SriXmlGeneratorService())->generarXmlFactura($comprobante)['xml'];

        $this->assertStringContainsString('<totalSinImpuestos>100.00</totalSinImpuestos>', $xml);
        $this->assertStringContainsString('<baseImponible>100.00</baseImponible><tarifa>15.00</tarifa><valor>15.00</valor>', $xml);
        $this->assertStringNotContainsString('<baseImponible>200.00</baseImponible>', $xml);
        $this->assertStringNotContainsString('<valor>30.00</valor>', $xml);
    }
}
