<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmisorController;
use App\Http\Controllers\LogoController;

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
    Route::get('/emisores/{id}', [EmisorController::class, 'show']);
    Route::put('/emisores/{id}', [EmisorController::class, 'update']);
    Route::delete('/emisores/{id}', [EmisorController::class, 'destroy']);
});


// Company logo routes
Route::get('/companies/{company}/logo', [LogoController::class, 'show']);
Route::post('/companies/{company}/logo', [LogoController::class, 'store'])->middleware('auth:sanctum');



