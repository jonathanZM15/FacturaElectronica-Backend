<?php

namespace App\Services;

use App\Models\Bodega;
use App\Models\MovimientoInventario;
use App\Models\MovimientoInventarioDetalle;
use App\Models\ProductoBodegaStock;
use App\Enums\TipoMovimientoInventario;
use App\Enums\TipoBodega;
use App\Exceptions\InvalidWarehouseOperationException;
use Illuminate\Support\Facades\DB;

class MovimientoInventarioService
{
    /**
     * Realiza una transferencia interna entre dos bodegas.
     */
    public function transferir(Bodega $origen, Bodega $destino, array $detalles, ?string $observacion, int $usuarioId): MovimientoInventario
    {
        if ($origen->tipo === TipoBodega::MERMAS) {
            throw new InvalidWarehouseOperationException("La bodega de mermas NO permite transferencias normales de salida. Usa transferirReacondicionado().");
        }

        if ($destino->tipo === TipoBodega::MERMAS && empty($observacion)) {
            throw new InvalidWarehouseOperationException("Las transferencias hacia MERMAS requieren obligatoriamente una justificación (observación).");
        }

        return DB::transaction(function () use ($origen, $destino, $detalles, $observacion, $usuarioId) {
            $movimiento = MovimientoInventario::create([
                'emisor_id' => $origen->emisor_id,
                'bodega_origen_id' => $origen->id,
                'bodega_destino_id' => $destino->id,
                'tipo_movimiento' => $destino->tipo === TipoBodega::MERMAS ? TipoMovimientoInventario::MERMA : TipoMovimientoInventario::TRANSFERENCIA_INTERNA,
                'observacion' => $observacion,
                'usuario_id' => $usuarioId,
            ]);

            foreach ($detalles as $detalle) {
                $this->descontarStock($origen, $detalle['producto_id'], $detalle['cantidad']);
                $this->incrementarStock($destino, $detalle['producto_id'], $detalle['cantidad']);
                $movimiento->detalles()->create($detalle);
            }

            return $movimiento;
        });
    }

    /**
     * Incrementa stock de forma independiente (ej. conteo de inventario sobrante).
     */
    public function ajustarPositivo(Bodega $destino, array $detalles, string $justificacion, int $usuarioId): MovimientoInventario
    {
        if (empty($justificacion)) {
            throw new InvalidWarehouseOperationException("Los ajustes positivos no son sustitutos de transferencias y requieren una justificación obligatoria.");
        }

        return DB::transaction(function () use ($destino, $detalles, $justificacion, $usuarioId) {
            $movimiento = MovimientoInventario::create([
                'emisor_id' => $destino->emisor_id,
                'bodega_destino_id' => $destino->id,
                'tipo_movimiento' => TipoMovimientoInventario::AJUSTE_POSITIVO,
                'observacion' => $justificacion,
                'usuario_id' => $usuarioId,
            ]);

            foreach ($detalles as $detalle) {
                $this->incrementarStock($destino, $detalle['producto_id'], $detalle['cantidad']);
                $movimiento->detalles()->create($detalle);
            }

            return $movimiento;
        });
    }

    /**
     * Reduce stock de forma independiente (ej. robo, daño severo).
     */
    public function ajustarNegativo(Bodega $origen, array $detalles, string $justificacion, int $usuarioId): MovimientoInventario
    {
        if (empty($justificacion)) {
            throw new InvalidWarehouseOperationException("Los ajustes negativos no son sustitutos de transferencias y requieren una justificación obligatoria.");
        }

        return DB::transaction(function () use ($origen, $detalles, $justificacion, $usuarioId) {
            $movimiento = MovimientoInventario::create([
                'emisor_id' => $origen->emisor_id,
                'bodega_origen_id' => $origen->id,
                'tipo_movimiento' => TipoMovimientoInventario::AJUSTE_NEGATIVO,
                'observacion' => $justificacion,
                'usuario_id' => $usuarioId,
            ]);

            foreach ($detalles as $detalle) {
                $this->descontarStock($origen, $detalle['producto_id'], $detalle['cantidad']);
                $movimiento->detalles()->create($detalle);
            }

            return $movimiento;
        });
    }

    /**
     * Único método permitido para sacar stock de MERMAS hacia otras bodegas.
     */
    public function transferirReacondicionado(Bodega $origenMermas, Bodega $destino, array $detalles, string $justificacion, int $usuarioId): MovimientoInventario
    {
        if ($origenMermas->tipo !== TipoBodega::MERMAS) {
            throw new InvalidWarehouseOperationException("Esta función es exclusiva para retirar stock reacondicionado de la bodega de MERMAS.");
        }

        if (!in_array($destino->tipo, [TipoBodega::VENTA, TipoBodega::ALMACEN, TipoBodega::EXHIBICION], true)) {
            throw new InvalidWarehouseOperationException("Solo se pueden enviar productos reacondicionados hacia Venta, Almacén o Exhibición.");
        }

        if (empty($justificacion)) {
            throw new InvalidWarehouseOperationException("Las transferencias de reacondicionamiento requieren una justificación obligatoria.");
        }

        return DB::transaction(function () use ($origenMermas, $destino, $detalles, $justificacion, $usuarioId) {
            $movimiento = MovimientoInventario::create([
                'emisor_id' => $origenMermas->emisor_id,
                'bodega_origen_id' => $origenMermas->id,
                'bodega_destino_id' => $destino->id,
                'tipo_movimiento' => TipoMovimientoInventario::TRANSFERENCIA_INTERNA,
                'observacion' => $justificacion,
                'usuario_id' => $usuarioId,
            ]);

            foreach ($detalles as $detalle) {
                $this->descontarStock($origenMermas, $detalle['producto_id'], $detalle['cantidad']);
                $this->incrementarStock($destino, $detalle['producto_id'], $detalle['cantidad']);
                $movimiento->detalles()->create($detalle);
            }

            return $movimiento;
        });
    }

    private function descontarStock(Bodega $bodega, int $productoId, float $cantidad): void
    {
        $stock = ProductoBodegaStock::where('producto_id', $productoId)
            ->where('bodega_id', $bodega->id)
            ->lockForUpdate() // Previene race conditions en el bloqueo de BD
            ->first();

        if (!$stock || $stock->stock_disponible < $cantidad) {
            throw new InvalidWarehouseOperationException("Stock disponible insuficiente en la bodega '{$bodega->nombre}' para realizar el movimiento.");
        }

        $stock->stock_fisico -= $cantidad;
        $stock->stock_disponible = max(0, $stock->stock_fisico - $stock->stock_reservado);
        $stock->save();
    }

    private function incrementarStock(Bodega $bodega, int $productoId, float $cantidad): void
    {
        $stock = ProductoBodegaStock::firstOrCreate(
            ['producto_id' => $productoId, 'bodega_id' => $bodega->id],
            ['stock_fisico' => 0, 'stock_disponible' => 0, 'stock_reservado' => 0]
        );

        $stock->stock_fisico += $cantidad;
        $stock->stock_disponible = max(0, $stock->stock_fisico - $stock->stock_reservado);
        $stock->save();
    }
}
