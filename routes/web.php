<?php

use Illuminate\Support\Facades\Route;

// Servir el frontend React
Route::get('/', function () {
    return view('app');
});

// Catch-all para rutas del frontend (SPA)
Route::get('{path?}', function () {
    return view('app');
})->where('path', '.*');
