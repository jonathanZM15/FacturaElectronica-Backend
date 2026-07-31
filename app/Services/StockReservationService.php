<?php

namespace App\Services;

use App\Models\Bodega;
use App\Models\ProductoBodegaStock;
use App\Services\BodegaValidationService;
use App\Exceptions\InvalidWarehouseOperationException;
use Illuminate\Support\Facades\DB;

class StockReservationService
{
    protected BodegaValidationService $bodegaValidation;

    public function __construct(BodegaValidationService $bodegaValidation)
    {
        $this->bodegaValidation = $bodegaValidation;
    }

    public function reservar(Bodega $bodega, int $productoId, float $cantidad): void
    {
        $this->bodegaValidation->validateStockReservation($bodega);

        DB::transaction(function () use ($bodega, $productoId, $cantidad) {
            $stock = ProductoBodegaStock::where('bodega_id', $bodega->id)
                ->where('producto_id', $productoId)
                ->lockForUpdate()
                ->first();

            if (!$stock || $stock->stock_disponible < $cantidad) {
                throw new InvalidWarehouseOperationException("Stock disponible insuficiente para reservar en la bodega '{$bodega->nombre}'.");
            }

            $stock->stock_reservado += $cantidad;
            $stock->stock_disponible = max(0, $stock->stock_fisico - $stock->stock_reservado);
            $stock->save();
        });
    }

    public function confirmarReserva(Bodega $bodega, int $productoId, float $cantidad): void
    {
        DB::transaction(function () use ($bodega, $productoId, $cantidad) {
            $stock = ProductoBodegaStock::where('bodega_id', $bodega->id)
                ->where('producto_id', $productoId)
                ->lockForUpdate()
                ->first();

            if (!$stock || $stock->stock_reservado < $cantidad) {
                throw new InvalidWarehouseOperationException("Stock reservado insuficiente para confirmar en la bodega '{$bodega->nombre}'.");
            }

            $stock->stock_fisico -= $cantidad;
            $stock->stock_reservado -= $cantidad;
            $stock->stock_disponible = max(0, $stock->stock_fisico - $stock->stock_reservado);
            $stock->save();
        });
    }

    public function liberarReserva(Bodega $bodega, int $productoId, float $cantidad): void
    {
        DB::transaction(function () use ($bodega, $productoId, $cantidad) {
            $stock = ProductoBodegaStock::where('bodega_id', $bodega->id)
                ->where('producto_id', $productoId)
                ->lockForUpdate()
                ->first();

            if (!$stock || $stock->stock_reservado < $cantidad) {
                throw new InvalidWarehouseOperationException("Stock reservado insuficiente para liberar en la bodega '{$bodega->nombre}'.");
            }

            $stock->stock_reservado -= $cantidad;
            $stock->stock_disponible = max(0, $stock->stock_fisico - $stock->stock_reservado);
            $stock->save();
        });
    }
}
