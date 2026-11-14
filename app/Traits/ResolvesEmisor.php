<?php

namespace App\Traits;

use App\Services\BusinessAccess;

trait ResolvesEmisor
{
    protected function resolveEmisorId($emisorId): int
    {
        $user = auth()->user();
        abort_unless($user, 401, 'No autenticado.');
        $id = filter_var($emisorId ?: $user->emisor_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_unless($id, 422, 'Debe especificar un emisor válido.');
        return (int) BusinessAccess::company($user, $id)->id;
    }
}
