<?php

namespace App\Enums;

enum TipoMovimientoInventario: string
{
    case INGRESO_INICIAL = 'INGRESO_INICIAL';
    case TRANSFERENCIA_INTERNA = 'TRANSFERENCIA_INTERNA';
    case AJUSTE_POSITIVO = 'AJUSTE_POSITIVO';
    case AJUSTE_NEGATIVO = 'AJUSTE_NEGATIVO';
    case MERMA = 'MERMA';
}
