<?php

use Illuminate\Support\Facades\Route;

// React se sirve por separado; el backend no depende de una vista Blade inexistente.
Route::get('/', function () {
    return response()->json(['service' => 'Facturación API', 'status' => 'ok']);
});
