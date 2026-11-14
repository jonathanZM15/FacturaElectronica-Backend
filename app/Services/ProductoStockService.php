<?php

namespace App\Services;

use App\Enums\TipoMovimientoInventario;
use App\Models\Producto;
use App\Models\Bodega;
use App\Models\MovimientoInventario;
use App\Models\ProductoBodegaStock;
use App\Enums\TipoProducto;
use App\Enums\TipoControlInventario;
use App\Exceptions\InvalidInventoryControlException;
use App\Exceptions\InvalidWarehouseOperationException;
use Illuminate\Support\Facades\DB;

class ProductoStockService
{
    /**
     * Procesa la solicitud de Inventario Inicial (MOV-01) aplicando las reglas 
     * según el tipo de producto y el tipo de control de inventario.
     *
     * @param Producto $producto
     * @param array $data Datos del stock (ej. cantidad, series, código lote, etc.)
     * @return void
     * @throws InvalidInventoryControlException
     */
    public function procesarStockInicial(Producto $producto, array $data): void
    {
        if ($producto->tipo === TipoProducto::SERVICIO) {
            throw new InvalidInventoryControlException("Un producto de tipo SERVICIO no participa en inventario y no puede tener stock inicial.");
        }

        if ($producto->tipo_control_inventario === TipoControlInventario::SIN_CONTROL) {
            throw new InvalidInventoryControlException("Un producto con control SIN_CONTROL no puede tener stock inicial.");
        }

        switch ($producto->tipo_control_inventario) {
            case TipoControlInventario::CANTIDAD:
                $this->validateCantidadControl($data);
                break;

            case TipoControlInventario::LOTE:
                $this->validateLoteControl($data);
                break;

            case TipoControlInventario::SERIE:
                $this->validateSerieControl($data);
                break;
        }

        $bodegaId = $data['bodega_destino_id'] ?? null;
        if (empty($bodegaId)) {
            throw new InvalidInventoryControlException("Para stock inicial se requiere enviar 'bodega_destino_id'.");
        }

        $bodega = Bodega::whereHas('establecimiento', fn ($q) => $q->where('emisor_id', $producto->emisor_id))->find($bodegaId);
        if (!$bodega) throw new InvalidWarehouseOperationException('La bodega destino no pertenece a la empresa.');
        $lotes = array_map(fn ($lote) => [
            'numero_lote' => $lote['codigo_lote'], 'cantidad' => $lote['cantidad_lote'],
            'fecha_fabricacion' => $lote['fecha_fabricacion'] ?? null,
            'fecha_vencimiento' => $lote['fecha_vencimiento'] ?? null,
        ], $data['lotes'] ?? []);
        $series = array_map(fn ($serie) => is_array($serie) ? $serie : ['numero_serie' => $serie], $data['series'] ?? []);
        $cantidad = $producto->tipo_control_inventario === TipoControlInventario::SERIE ? count($series)
            : ($producto->tipo_control_inventario === TipoControlInventario::LOTE ? array_sum(array_column($lotes, 'cantidad')) : $data['cantidad']);
        app(MovimientoInventarioService::class)->inventarioInicial($bodega, [[
            'producto_id' => $producto->id, 'cantidad' => $cantidad, 'lotes' => $lotes, 'series' => $series,
            'costo_unitario' => $data['costo_unitario'] ?? 0,
        ]], 'Inventario inicial del producto', (int) auth()->id(), $data['motivo_id'] ?? null);
    }

    private function validateCantidadControl(array $data): void
    {
        if (!isset($data['cantidad']) || !is_numeric($data['cantidad']) || (float)$data['cantidad'] <= 0) {
            throw new InvalidInventoryControlException("Para productos controlados por CANTIDAD se requiere enviar una 'cantidad' mayor a 0.");
        }
    }

    private function validateLoteControl(array $data): void
    {
        if (empty($data['lotes']) || !is_array($data['lotes'])) {
            throw new InvalidInventoryControlException("Para productos controlados por LOTE se requiere enviar un arreglo 'lotes'.");
        }

        foreach ($data['lotes'] as $index => $lote) {
            if (empty($lote['codigo_lote'])) {
                throw new InvalidInventoryControlException("El lote #{$index} requiere un 'codigo_lote'.");
            }
            if (!isset($lote['cantidad_lote']) || !is_numeric($lote['cantidad_lote']) || (float)$lote['cantidad_lote'] <= 0) {
                throw new InvalidInventoryControlException("El lote #{$index} requiere una 'cantidad_lote' mayor a 0.");
            }
        }
    }

    private function validateSerieControl(array $data): void
    {
        if (empty($data['series']) || !is_array($data['series'])) {
            throw new InvalidInventoryControlException("Para productos controlados por SERIE se requiere enviar un arreglo 'series'.");
        }

        $seriesCount = count($data['series']);

        if (isset($data['cantidad'])) {
            $expectedCount = (int)$data['cantidad'];
            if ($seriesCount != $expectedCount) {
                throw new InvalidInventoryControlException("La cantidad total especificada ({$expectedCount}) no coincide con la cantidad de series enviadas ({$seriesCount}).");
            }
        }

        $seriesValues = array_map(function ($s) {
            return is_array($s) ? ($s['numero_serie'] ?? '') : $s;
        }, $data['series']);

        $uniqueSeries = array_unique($seriesValues);
        if (count($uniqueSeries) !== $seriesCount) {
            throw new InvalidInventoryControlException("El arreglo de series contiene números de serie duplicados.");
        }
    }
}
