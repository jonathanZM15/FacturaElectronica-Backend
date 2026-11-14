<?php

namespace App\Services;

use App\Models\{Producto, TipoImpuesto};

class ProductTaxService
{
    public static function resolve(Producto $product): ?TipoImpuesto
    {
        $query = TipoImpuesto::where('tipo_impuesto', 'IVA')->where('estado', 'Activo');
        if ($product->tipo_impuesto_id) {
            $tax = $query->whereKey($product->tipo_impuesto_id)->first();
            if ($tax && !TipoImpuesto::esCombinacionIvaValida((int) $tax->codigo_porcentaje, (float) $tax->valor_tarifa)) {
                return null;
            }
            return $tax;
        }
        // Legacy labels are accepted only when the active catalog match is unique.
        $label = trim(strtoupper((string) $product->tipo_iva));
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*%?$/', $label, $matches)) {
            $query->where('valor_tarifa', str_replace(',', '.', $matches[1]));
        } elseif (in_array($label, ['NO OBJETO', 'NO_OBJETO'], true)) {
            $query->where('codigo_porcentaje', 6);
        } elseif ($label === 'EXENTO') {
            $query->where('codigo_porcentaje', 7);
        } else {
            return null;
        }
        $matches = $query->limit(2)->get();
        if ($matches->count() === 1) {
            $tax = $matches->first();
            if ($tax && TipoImpuesto::esCombinacionIvaValida((int) $tax->codigo_porcentaje, (float) $tax->valor_tarifa)) {
                return $tax;
            }
        }
        return null;
    }
}
