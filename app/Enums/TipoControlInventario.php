<?php

namespace App\Enums;

enum TipoControlInventario: string
{
    case SIN_CONTROL = 'SIN_CONTROL';
    case CANTIDAD = 'CANTIDAD';
    case LOTE = 'LOTE';
    case SERIE = 'SERIE';
}
