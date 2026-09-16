<?php

namespace App\Enums;

enum EstadoOperativoTransferencia: string
{
    case PENDIENTE_DE_ENVIO = 'PENDIENTE_DE_ENVIO';
    case EN_TRANSITO = 'EN_TRANSITO';
    case EN_RECEPCION = 'EN_RECEPCION';
    case RECIBIDA_CORRECTAMENTE = 'RECIBIDA_CORRECTAMENTE';
    case RECIBIDA_CON_INCIDENCIA = 'RECIBIDA_CON_INCIDENCIA';
}

