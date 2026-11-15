<?php

namespace App\Services;

use App\Models\{Producto, TipoImpuesto};

class TaxValidityService
{
    public function error(TipoImpuesto $tax, string $date, ?Producto $product = null): ?string
    {
        if ($tax->tipo_impuesto !== 'IVA' || !TipoImpuesto::esCombinacionIvaValida((int) $tax->codigo_porcentaje, (float) $tax->valor_tarifa)) {
            return 'La combinación de impuesto y tarifa no corresponde al catálogo de IVA soportado.';
        }
        $reviewUntil = config('iva.reviewed_until');
        if (!$reviewUntil || $date > $reviewUntil) return 'Se requiere revisar la vigencia normativa del catálogo de IVA antes de emitir en esta fecha.';
        $special = in_array((int) $tax->codigo_porcentaje, [5, 8], true);
        foreach (config($special ? 'iva.special_periods' : 'iva.periods', []) as $period) {
            if ((int) ($period['code'] ?? -1) !== (int) $tax->codigo_porcentaje
                || abs((float) ($period['rate'] ?? -1) - (float) $tax->valor_tarifa) > .0001
                || empty($period['source']) || empty($period['from']) || $date < $period['from']
                || (!empty($period['to']) && $date > $period['to'])) continue;
            if ($special && (!$product || !in_array((int) $product->id, array_map('intval', $period['eligible_product_ids'] ?? []), true))) continue;
            if ((int) $tax->codigo_porcentaje === 8 && empty($period['to'])) continue;
            if ((int) $tax->codigo_porcentaje === 5 && !in_array($product?->codigo_auxiliar, [
                'F010101', 'F010201', 'F010202', 'F010203', 'F010204', 'F010205', 'F010301',
                'F010401', 'F010402', 'F010501', 'F010601', 'F010701', 'F010702', 'F010703',
                'F010801', 'F010802', 'F010803', 'F010804',
            ], true)) continue;
            return null;
        }
        return 'La tarifa de IVA no está habilitada para esta fecha y producto. Revisa su vigencia y sustento antes de vender.';
    }
}
