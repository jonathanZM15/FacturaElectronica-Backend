<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password-recovery', [AuthController::class, 'passwordRecovery']);
Route::post('/password-reset', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/cambiarClave', [AuthController::class, 'cambiarPassword']);
});

use App\Http\Controllers\LogoController;

// Company logo routes
Route::get('/companies/{company}/logo', [LogoController::class, 'show']);
Route::post('/companies/{company}/logo', [LogoController::class, 'store'])->middleware('auth:sanctum');
