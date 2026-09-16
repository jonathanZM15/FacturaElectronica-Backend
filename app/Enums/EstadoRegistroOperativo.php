<?php

namespace App\Enums;

enum EstadoRegistroOperativo: string
{
    case BORRADOR = 'BORRADOR';
    case EN_PROCESO = 'EN_PROCESO';
    case CONFIRMADO = 'CONFIRMADO';
}

