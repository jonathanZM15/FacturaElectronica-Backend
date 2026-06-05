<?php

namespace Database\Seeders;

use App\Models\TipoImpuesto;
use Illuminate\Database\Seeder;

class TiposImpuestoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            [
                'tipo_impuesto' => 'IVA',
                'tipo_tarifa' => 'Porcentaje',
                'codigo_impuesto' => 2,
                'codigo_porcentaje' => 0,
                'nombre' => 'IVA 0%',
                'valor_tarifa' => 0.00,
                'estado' => 'Activo',
            ],
            [
                'tipo_impuesto' => 'IVA',
                'tipo_tarifa' => 'Porcentaje',
                'codigo_impuesto' => 2,
                'codigo_porcentaje' => 2,
                'nombre' => 'IVA 12%',
                'valor_tarifa' => 12.00,
                'estado' => 'Activo',
            ],
            [
                'tipo_impuesto' => 'IVA',
                'tipo_tarifa' => 'Porcentaje',
                'codigo_impuesto' => 2,
                'codigo_porcentaje' => 3,
                'nombre' => 'IVA 14%',
                'valor_tarifa' => 14.00,
                'estado' => 'Activo',
            ],
            [
                'tipo_impuesto' => 'IVA',
                'tipo_tarifa' => 'Porcentaje',
                'codigo_impuesto' => 2,
                'codigo_porcentaje' => 4,
                'nombre' => 'IVA 15%',
                'valor_tarifa' => 15.00,
                'estado' => 'Activo',
            ],
            [
                'tipo_impuesto' => 'IVA',
                'tipo_tarifa' => 'Porcentaje',
                'codigo_impuesto' => 2,
                'codigo_porcentaje' => 6,
                'nombre' => 'No objeto de IVA',
                'valor_tarifa' => 0.00,
                'estado' => 'Activo',
            ],
            [
                'tipo_impuesto' => 'IVA',
                'tipo_tarifa' => 'Porcentaje',
                'codigo_impuesto' => 2,
                'codigo_porcentaje' => 7,
                'nombre' => 'Exento de IVA',
                'valor_tarifa' => 0.00,
                'estado' => 'Activo',
            ],
        ];

        foreach ($tipos as $tipo) {
            TipoImpuesto::updateOrCreate(
                [
                    'tipo_impuesto' => $tipo['tipo_impuesto'],
                    'codigo_porcentaje' => $tipo['codigo_porcentaje'],
                ],
                [
                    'tipo_tarifa' => $tipo['tipo_tarifa'],
                    'codigo_impuesto' => $tipo['codigo_impuesto'],
                    'nombre' => $tipo['nombre'],
                    'valor_tarifa' => $tipo['valor_tarifa'],
                    'estado' => $tipo['estado'],
                    'created_by_id' => null,
                    'updated_by_id' => null,
                ]
            );
        }
    }
}
