<?php

namespace App\Services;

use App\Enums\SriEstadoComprobante;

class SriStateMachineService
{
    public function assertTransition(?string $from, string $to): void
    {
        $current = $from ? SriEstadoComprobante::tryFrom($from) : null;
        $next = SriEstadoComprobante::tryFrom($to);

        if (!$next) {
            throw new \InvalidArgumentException('Estado SRI no valido: ' . $to);
        }

        if ($current && !$current->canTransitionTo($next)) {
            throw new \RuntimeException("Transicion SRI no permitida de {$current->value} a {$next->value}.");
        }
    }
}