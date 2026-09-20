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

        $motivosPorTipo = [
            TipoMovimientoInventario::MOV_01_INVENTARIO_INICIAL->value => [
                ['codigo' => 'INI-01', 'descripcion' => 'Carga inicial de inventario'],
                ['codigo' => 'INI-02', 'descripcion' => 'Migración inicial de inventario'],
                ['codigo' => 'INI-03', 'descripcion' => 'Apertura de nueva bodega'],
                ['codigo' => 'INI-04', 'descripcion' => 'Apertura de nuevo establecimiento'],
                ['codigo' => 'INI-05', 'descripcion' => 'Registro inicial por implementación del sistema'],
                ['codigo' => 'INI-99', 'descripcion' => 'Otro'],
            ],
            TipoMovimientoInventario::MOV_06_TRANSFERENCIA_INTERNA->value => [
                ['codigo' => 'TRA-01', 'descripcion' => 'Reabastecimiento interno'],
                ['codigo' => 'TRA-02', 'descripcion' => 'Reorganización interna'],
                ['codigo' => 'TRA-03', 'descripcion' => 'Reposición a bodega de venta'],
                ['codigo' => 'TRA-04', 'descripcion' => 'Traslado a exhibición'],
                ['codigo' => 'TRA-05', 'descripcion' => 'Retorno desde exhibición'],
                ['codigo' => 'TRA-06', 'descripcion' => 'Optimización de inventario interno'],
                ['codigo' => 'TRA-99', 'descripcion' => 'Otro'],
            ],
            TipoMovimientoInventario::MOV_07_TRANSFERENCIA_SUCURSALES->value => [
                ['codigo' => 'TRS-01', 'descripcion' => 'Reabastecimiento entre sucursales'],
                ['codigo' => 'TRS-02', 'descripcion' => 'Solicitud de abastecimiento'],
                ['codigo' => 'TRS-03', 'descripcion' => 'Redistribución de inventario'],
                ['codigo' => 'TRS-04', 'descripcion' => 'Apertura de nueva sucursal'],
                ['codigo' => 'TRS-05', 'descripcion' => 'Cierre de sucursal'],
                ['codigo' => 'TRS-06', 'descripcion' => 'Traslado por baja rotación'],
                ['codigo' => 'TRS-99', 'descripcion' => 'Otro'],
            ],
            TipoMovimientoInventario::MOV_09_AJUSTE_POSITIVO->value => [
                ['codigo' => 'AJP-01', 'descripcion' => 'Diferencia positiva por conteo físico'],
                ['codigo' => 'AJP-02', 'descripcion' => 'Producto encontrado en bodega'],
                ['codigo' => 'AJP-03', 'descripcion' => 'Corrección de error operativo'],
                ['codigo' => 'AJP-04', 'descripcion' => 'Regularización de lote'],
                ['codigo' => 'AJP-05', 'descripcion' => 'Regularización de serie'],
                ['codigo' => 'AJP-06', 'descripcion' => 'Corrección por migración de inventario'],
                ['codigo' => 'AJP-99', 'descripcion' => 'Otro'],
            ],
            TipoMovimientoInventario::MOV_10_AJUSTE_NEGATIVO->value => [
                ['codigo' => 'AJN-01', 'descripcion' => 'Diferencia negativa por conteo físico'],
                ['codigo' => 'AJN-02', 'descripcion' => 'Faltante detectado en bodega'],
                ['codigo' => 'AJN-03', 'descripcion' => 'Pérdida no asociada a venta'],
                ['codigo' => 'AJN-04', 'descripcion' => 'Corrección de error operativo'],
                ['codigo' => 'AJN-05', 'descripcion' => 'Regularización de lote'],
                ['codigo' => 'AJN-06', 'descripcion' => 'Regularización de serie'],
                ['codigo' => 'AJN-07', 'descripcion' => 'Corrección por migración de inventario'],
                ['codigo' => 'AJN-99', 'descripcion' => 'Otro'],
            ],
            TipoMovimientoInventario::MOV_11_ENVIO_MERMAS->value => [
                ['codigo' => 'MER-01', 'descripcion' => 'Producto dañado'],
                ['codigo' => 'MER-02', 'descripcion' => 'Producto vencido'],
                ['codigo' => 'MER-03', 'descripcion' => 'Producto deteriorado'],
                ['codigo' => 'MER-04', 'descripcion' => 'Producto roto o incompleto'],
                ['codigo' => 'MER-05', 'descripcion' => 'Producto contaminado'],
                ['codigo' => 'MER-06', 'descripcion' => 'Producto no apto para la venta'],
                ['codigo' => 'MER-07', 'descripcion' => 'Producto retirado por control interno'],
                ['codigo' => 'MER-08', 'descripcion' => 'Producto retirado por revisión de calidad'],
                ['codigo' => 'MER-99', 'descripcion' => 'Otro'],
            ],
            TipoMovimientoInventario::MOV_12_REACONDICIONAMIENTO_MERMAS->value => [
                ['codigo' => 'REC-01', 'descripcion' => 'Producto reparado'],
                ['codigo' => 'REC-02', 'descripcion' => 'Producto revisado y apto'],
                ['codigo' => 'REC-03', 'descripcion' => 'Producto limpiado o restaurado'],
                ['codigo' => 'REC-04', 'descripcion' => 'Producto recuperado por control de calidad'],
                ['codigo' => 'REC-05', 'descripcion' => 'Corrección de clasificación en mermas'],
                ['codigo' => 'REC-06', 'descripcion' => 'Producto recuperado para exhibición'],
                ['codigo' => 'REC-99', 'descripcion' => 'Otro'],
            ],
        ];

        foreach ($emisores as $emisorId) {
            foreach ($motivosPorTipo as $tipoMovimiento => $motivos) {
                foreach ($motivos as $motivo) {
                    DB::table('motivos_movimiento')->updateOrInsert(
                        [
                            'emisor_id' => $emisorId,
                            'codigo' => $motivo['codigo'],
                        ],
                        [
                            'descripcion' => $motivo['descripcion'],
                            'tipo_movimiento' => $tipoMovimiento,
                            'activo' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }
    }
}
