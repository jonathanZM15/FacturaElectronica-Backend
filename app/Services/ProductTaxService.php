<?php

namespace App\Services;

use App\Models\{Producto, TipoImpuesto};

class ProductTaxService
{
    /** Resolver el catálogo una sola vez por colección, sin caché entre peticiones. */
    public static function preload(iterable $products): void
    {
        $products = collect($products);
        if ($products->isEmpty()) return;

        $taxes = TipoImpuesto::where('tipo_impuesto', 'IVA')->where('estado', 'Activo')->get();
        $byId = $taxes->keyBy('id');
        foreach ($products as $product) {
            $tax = null;
            if ($product->tipo_impuesto_id) {
                $tax = $byId->get($product->tipo_impuesto_id);
            } else {
                $label = trim(strtoupper((string) $product->tipo_iva));
                $matches = collect();
                if (preg_match('/^(\d+(?:[.,]\d+)?)\s*%?$/', $label, $parts)) {
                    $rate = self::normalizeRate($parts[1]);
                    $matches = $taxes->filter(fn ($candidate) => self::normalizeRate((string) $candidate->getRawOriginal('valor_tarifa')) === $rate);
                } elseif (in_array($label, ['NO OBJETO', 'NO_OBJETO'], true)) {
                    $matches = $taxes->where('codigo_porcentaje', 6);
                } elseif ($label === 'EXENTO') {
                    $matches = $taxes->where('codigo_porcentaje', 7);
                }
                if ($matches->count() === 1) $tax = $matches->first();
            }
            if ($tax && !TipoImpuesto::esCombinacionIvaValida((int) $tax->codigo_porcentaje, (float) $tax->valor_tarifa)) {
                $tax = null;
            }
            $product->setRelation('resolvedImpuesto', $tax);
        }
    }

    private static function normalizeRate(string $rate): string
    {
        [$integer, $fraction] = array_pad(explode('.', str_replace(',', '.', $rate), 2), 2, '');
        $integer = ltrim($integer, '0');
        $fraction = rtrim($fraction, '0');
        return ($integer === '' ? '0' : $integer).($fraction === '' ? '' : '.'.$fraction);
    }

    public static function resolve(Producto $product): ?TipoImpuesto
    {
        if ($product->relationLoaded('resolvedImpuesto')) return $product->getRelation('resolvedImpuesto');

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
