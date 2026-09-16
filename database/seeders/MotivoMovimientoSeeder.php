<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Enums\TipoMovimientoInventario;

class MotivoMovimientoSeeder extends Seeder
{
    public function run(): void
    {
        $emisores = DB::table('emisores')->pluck('id');

        $motivosTransferencia = [
            ['codigo' => 'TRA-01', 'descripcion' => 'Reabastecimiento'],
            ['codigo' => 'TRA-02', 'descripcion' => 'Reorganización interna'],
            ['codigo' => 'TRA-03', 'descripcion' => 'Optimización de inventario'],
            ['codigo' => 'TRA-04', 'descripcion' => 'Apertura de nueva sucursal'],
            ['codigo' => 'TRA-05', 'descripcion' => 'Cierre de sucursal'],
            ['codigo' => 'TRA-06', 'descripcion' => 'Solicitud de otra sucursal'],
            ['codigo' => 'TRA-99', 'descripcion' => 'Otro'],
        ];

        foreach ($emisores as $emisorId) {
            foreach ($motivosTransferencia as $motivo) {
                DB::table('motivos_movimiento')->updateOrInsert(
                    ['emisor_id' => $emisorId, 'codigo' => $motivo['codigo']],
                    [
                        'descripcion' => $motivo['descripcion'],
                        'tipo_movimiento' => TipoMovimientoInventario::MOV_06_TRANSFERENCIA_INTERNA->value,
                        'activo' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
