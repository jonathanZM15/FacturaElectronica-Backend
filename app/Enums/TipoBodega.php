<?php

namespace App\Enums;

enum TipoBodega: string
{
    case VENTA = 'VENTA';
    case ALMACEN = 'ALMACEN';
    case EXHIBICION = 'EXHIBICION';
    case MERMAS = 'MERMAS';
    case TRANSITO = 'TRANSITO';
}
