<?php

namespace App\Services;

use App\Models\Bodega;
use App\Enums\TipoBodega;
use App\Exceptions\InvalidWarehouseOperationException;

class BodegaValidationService
{
    /**
     * Valida si una bodega puede recibir ingresos por compras.
     * VENTA: NO permite.
     * EXHIBICION: NO permite.
     * MERMAS: NO permite.
     * TRANSITO: NO permite.
     * ALMACEN: Permite.
     */
    public function validatePurchaseReception(Bodega $bodega): void
    {
        $this->validateManualOperation($bodega);

        if ($bodega->tipo !== TipoBodega::ALMACEN) {
            throw new InvalidWarehouseOperationException("Solo las bodegas de ALMACÉN pueden recibir compras directamente. Bodega seleccionada: {$bodega->tipo->value}.");
        }
    }

    /**
     * Valida si una bodega permite realizar salidas por ventas.
     * VENTA: Permite.
     * ALMACEN: Puede participar (en ventas tipo despacho).
     */
    public function validateSale(Bodega $bodega): void
    {
        $this->validateManualOperation($bodega);

        if (!in_array($bodega->tipo, [TipoBodega::VENTA, TipoBodega::ALMACEN], true)) {
            throw new InvalidWarehouseOperationException("La bodega tipo {$bodega->tipo->value} no permite ventas.");
        }
    }

    /**
     * Valida si una bodega permite la reserva de stock (ventas tipo despacho).
     * EXHIBICION, MERMAS, TRANSITO: NO permiten.
     */
    public function validateStockReservation(Bodega $bodega): void
    {
        $this->validateManualOperation($bodega);

        if (!in_array($bodega->tipo, [TipoBodega::VENTA, TipoBodega::ALMACEN], true)) {
            throw new InvalidWarehouseOperationException("La bodega tipo {$bodega->tipo->value} no permite reserva de stock.");
        }
    }

    /**
     * Valida que la bodega no sea de TRÁNSITO, ya que el tránsito es de uso estrictamente
     * automático del sistema y no permite manipulaciones manuales por el usuario.
     */
    public function validateManualOperation(Bodega $bodega): void
    {
        if ($bodega->tipo === TipoBodega::TRANSITO) {
            throw new InvalidWarehouseOperationException("La bodega de TRÁNSITO es de uso interno del sistema y no permite operaciones manuales.");
        }
    }

    /**
     * Valida si la bodega puede recibir transferencias de ingreso.
     * ALMACEN: Permite.
     * VENTA: Permite.
     * EXHIBICION: Permite.
     * MERMAS: Permite.
     */
    public function validateTransferReception(Bodega $bodega): void
    {
        $this->validateManualOperation($bodega);
        // Todas las demás permiten entrada por transferencia (las reglas específicas de origen pueden aplicar aparte)
    }
}
