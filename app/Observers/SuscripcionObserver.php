<?php

namespace App\Observers;

use App\Models\Suscripcion;

class SuscripcionObserver
{
    /**
     * Handle the Suscripcion "created" event.
     * Resetear last_activity_at cuando se crea una nueva suscripción
     */
    public function created(Suscripcion $suscripcion): void
    {
        if ($suscripcion->emisor_id) {
            $suscripcion->emisor->update([
                'last_activity_at' => now()
            ]);
        }
    }

    /**
     * Handle the Suscripcion "updated" event.
     * Resetear last_activity_at cuando se renueva/modifica suscripción
     */
    public function updated(Suscripcion $suscripcion): void
    {
        // Si el estado cambió a "Vigente", es una renovación
        if ($suscripcion->isDirty('estado_suscripcion') && $suscripcion->estado_suscripcion === 'Vigente') {
            if ($suscripcion->emisor_id) {
                $suscripcion->emisor->update([
                    'last_activity_at' => now()
                ]);
            }
        }
    }

    /**
     * Handle the Suscripcion "deleted" event.
     */
    public function deleted(Suscripcion $suscripcion): void
    {
        //
    }

    /**
     * Handle the Suscripcion "restored" event.
     */
    public function restored(Suscripcion $suscripcion): void
    {
        if ($suscripcion->emisor_id) {
            $suscripcion->emisor->update([
                'last_activity_at' => now()
            ]);
        }
    }

    /**
     * Handle the Suscripcion "force deleted" event.
     */
    public function forceDeleted(Suscripcion $suscripcion): void
    {
        //
    }
}
