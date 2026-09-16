<?php

namespace App\Enums;

enum EstadoSerie: string
{
    case DISPONIBLE = 'DISPONIBLE';
    case BLOQUEADA = 'BLOQUEADA';
    case RESERVADA = 'RESERVADA';
    case EN_TRANSITO = 'EN_TRANSITO';
    case DANADA = 'DAÑADA';
    case VENDIDA = 'VENDIDA';
    case DEVUELTA_A_PROVEEDOR = 'DEVUELTA_A_PROVEEDOR';
    case BAJA_POR_AJUSTE = 'BAJA_POR_AJUSTE';
    case FALTANTE = 'FALTANTE';
}
