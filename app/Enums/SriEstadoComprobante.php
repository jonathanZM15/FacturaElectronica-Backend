<?php

namespace App\Enums;

enum SriEstadoComprobante: string
{
    case BORRADOR = 'BORRADOR';
    case FIRMADO = 'FIRMADO';
    case ENVIADO = 'ENVIADO';
    case RECIBIDA = 'RECIBIDA';
    case DEVUELTA = 'DEVUELTA';
    case AUTORIZADO = 'AUTORIZADO';
    case NO_AUTORIZADO = 'NO_AUTORIZADO';
    case PROCESANDO = 'PROCESANDO';
    case ERROR_FIRMA = 'ERROR_FIRMA';
    case ERROR_ENVIO = 'ERROR_ENVIO';
    case ERROR_AUTORIZACION = 'ERROR_AUTORIZACION';
    case ERROR_SISTEMA = 'ERROR_SISTEMA';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::BORRADOR => in_array($next, [self::PROCESANDO, self::FIRMADO, self::ERROR_FIRMA], true),
            self::PROCESANDO => in_array($next, [self::FIRMADO, self::ERROR_FIRMA, self::ERROR_SISTEMA], true),
            self::FIRMADO => in_array($next, [self::ENVIADO, self::ERROR_ENVIO, self::ERROR_SISTEMA], true),
            self::ENVIADO => in_array($next, [self::RECIBIDA, self::DEVUELTA, self::ERROR_ENVIO, self::ERROR_SISTEMA], true),
            self::RECIBIDA => in_array($next, [self::AUTORIZADO, self::NO_AUTORIZADO, self::ERROR_AUTORIZACION, self::ERROR_SISTEMA], true),
            self::DEVUELTA => in_array($next, [self::ENVIADO, self::ERROR_ENVIO, self::ERROR_SISTEMA], true),
            self::AUTORIZADO => false,
            self::NO_AUTORIZADO => false,
            self::ERROR_FIRMA, self::ERROR_ENVIO, self::ERROR_AUTORIZACION, self::ERROR_SISTEMA => true,
        };
    }
}