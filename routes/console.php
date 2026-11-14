<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// P09: Reconciliación programada de comprobantes detenidos sin trabajo en cola
Schedule::command('sri:reconciliar --minutes=10')->everyFifteenMinutes();
