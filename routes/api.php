<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmisorController;
use App\Http\Controllers\LogoController;
use App\Http\Controllers\PuntoEmisionController;

//rutas para inicio y registro de sesion
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password-recovery', [AuthController::class, 'passwordRecovery']);
Route::post('/password-reset', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    //rutas para cierre de sesión y cambiar clave
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/cambiarClave', [AuthController::class, 'cambiarPassword']);

    //Rutas para el emisor
    Route::get('/emisores', [EmisorController::class, 'index']);
    Route::post('/emisores', [EmisorController::class, 'store']);
    Route::get('/emisores/check-ruc/{ruc}', [EmisorController::class, 'checkRuc']);
    Route::get('/emisores/{id}', [EmisorController::class, 'show']);
    Route::put('/emisores/{id}', [EmisorController::class, 'update']);
    Route::delete('/emisores/{id}', [EmisorController::class, 'destroy']);
    Route::post('/emisores/{id}/prepare-deletion', [EmisorController::class, 'prepareDeletion']);
    Route::delete('/emisores/{id}/permanent', [EmisorController::class, 'destroyWithHistory']);

    // Establecimientos (sucursales) relacionados a un emisor
    Route::get('/emisores/{id}/establecimientos', [App\Http\Controllers\EstablecimientoController::class, 'index']);
    Route::get('/emisores/{id}/establecimientos/check-code/{code}', [App\Http\Controllers\EstablecimientoController::class, 'checkCode']);
    Route::post('/emisores/{id}/establecimientos', [App\Http\Controllers\EstablecimientoController::class, 'store']);
    Route::get('/emisores/{id}/establecimientos/{est}', [App\Http\Controllers\EstablecimientoController::class, 'show']);
    Route::put('/emisores/{id}/establecimientos/{est}', [App\Http\Controllers\EstablecimientoController::class, 'update']);
    Route::delete('/emisores/{id}/establecimientos/{est}', [App\Http\Controllers\EstablecimientoController::class, 'destroy']);

    // Puntos de Emisión relacionados a un establecimiento
    Route::get('/emisores/{id}/establecimientos/{est}/puntos', [PuntoEmisionController::class, 'index']);
    Route::post('/emisores/{id}/establecimientos/{est}/puntos', [PuntoEmisionController::class, 'store']);
    Route::get('/emisores/{id}/establecimientos/{est}/puntos/{punto}', [PuntoEmisionController::class, 'show']);
    Route::put('/emisores/{id}/establecimientos/{est}/puntos/{punto}', [PuntoEmisionController::class, 'update']);
    Route::delete('/emisores/{id}/establecimientos/{est}/puntos/{punto}', [PuntoEmisionController::class, 'destroy']);
});


// Company logo routes
Route::get('/companies/{company}/logo', [LogoController::class, 'show']);
Route::get('/companies/{company}/logo-file', [LogoController::class, 'file'])->name('companies.logo.file');
Route::post('/companies/{company}/logo', [LogoController::class, 'store'])->middleware('auth:sanctum');



