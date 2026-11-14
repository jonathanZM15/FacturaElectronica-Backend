<?php

namespace App\Services;

use App\Models\{Cliente, Company, Comprobante, Establecimiento};

class InvoiceSnapshots
{
    public static function apply(Comprobante $invoice): void
    {
        foreach (['cliente' => Cliente::class, 'company' => Company::class, 'establecimiento' => Establecimiento::class] as $relation => $model) {
            $field = ($relation === 'company' ? 'emisor' : $relation).'_snapshot';
            if ($snapshot = $invoice->$field) {
                $record = new $model;
                $record->setRawAttributes($snapshot, true);
                $invoice->setRelation($relation, $record);
            }
        }
    }
}
