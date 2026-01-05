<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmisorController;
use App\Http\Controllers\LogoController;
use App\Http\Controllers\PuntoEmisionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SuscripcionController;
use App\Http\Controllers\TipoImpuestoController;
use App\Http\Controllers\TipoRetencionController;

//rutas para inicio y registro de sesion
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password-recovery', [AuthController::class, 'passwordRecovery']);
Route::post('/password-reset', [AuthController::class, 'resetPassword']);

// Rutas públicas para verificación de email y cambio de contraseña inicial
Route::post('/verify-email', [UserController::class, 'verifyEmail']);
Route::post('/change-initial-password', [UserController::class, 'changeInitialPassword']);

// Rutas públicas para verificar disponibilidad de usuario, cédula y email
Route::get('/usuarios/check/username', [UserController::class, 'checkUsername']);
Route::get('/usuarios/check/cedula', [UserController::class, 'checkCedula']);
Route::get('/usuarios/check/email', [UserController::class, 'checkEmail']);

Route::middleware('auth:sanctum')->group(function () {
    //rutas para cierre de sesión y cambiar clave
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/cambiarClave', [AuthController::class, 'cambiarPassword']);

    // Rutas de Usuarios (solo admins)
    Route::middleware('admin')->group(function () {
        Route::get('/usuarios', [UserController::class, 'index']);
        Route::post('/usuarios', [UserController::class, 'store']);
        Route::get('/usuarios/{usuario}', [UserController::class, 'show']);
        Route::put('/usuarios/{usuario}', [UserController::class, 'update']);
        Route::delete('/usuarios/{usuario}', [UserController::class, 'destroy']);
        Route::post('/usuarios/{usuario}/resend-verification', [UserController::class, 'resendVerificationEmail']);
        
        // Rutas de Planes (solo admins)
        Route::get('/planes', [PlanController::class, 'index']);
        Route::post('/planes', [PlanController::class, 'store']);
        Route::get('/planes/periodos', [PlanController::class, 'periodos']);
        Route::get('/planes/estados', [PlanController::class, 'estados']);
        Route::get('/planes/{id}', [PlanController::class, 'show']);
        Route::put('/planes/{id}', [PlanController::class, 'update']);
        Route::delete('/planes/{id}', [PlanController::class, 'destroy']);
        
        // Rutas de Tipos de Impuesto (solo admins) - Módulo 7
        Route::get('/tipos-impuesto', [TipoImpuestoController::class, 'index']);
        Route::post('/tipos-impuesto', [TipoImpuestoController::class, 'store']);
        Route::get('/tipos-impuesto/opciones', [TipoImpuestoController::class, 'opciones']);
        Route::get('/tipos-impuesto/activos', [TipoImpuestoController::class, 'activos']);
        Route::get('/tipos-impuesto/check-codigo', [TipoImpuestoController::class, 'checkCodigo']);
        Route::get('/tipos-impuesto/check-nombre', [TipoImpuestoController::class, 'checkNombre']);
        Route::get('/tipos-impuesto/{id}', [TipoImpuestoController::class, 'show']);
        Route::put('/tipos-impuesto/{id}', [TipoImpuestoController::class, 'update']);
        Route::delete('/tipos-impuesto/{id}', [TipoImpuestoController::class, 'destroy']);
        
        // Rutas de Tipos de Retención (solo admins) - Módulo 7
        Route::get('/tipos-retencion', [TipoRetencionController::class, 'index']);
        Route::post('/tipos-retencion', [TipoRetencionController::class, 'store']);
        Route::get('/tipos-retencion/opciones', [TipoRetencionController::class, 'getOpciones']);
        Route::get('/tipos-retencion/check-codigo', [TipoRetencionController::class, 'checkCodigo']);
        Route::get('/tipos-retencion/{id}', [TipoRetencionController::class, 'show']);
        Route::put('/tipos-retencion/{id}', [TipoRetencionController::class, 'update']);
        Route::delete('/tipos-retencion/{id}', [TipoRetencionController::class, 'destroy']);
    });

    //Rutas para el emisor
    Route::get('/emisores', [EmisorController::class, 'index']);
    Route::post('/emisores', [EmisorController::class, 'store']);
    Route::get('/emisores/check-ruc/{ruc}', [EmisorController::class, 'checkRuc']);
    
    // Rutas anidadas específicas (deben estar ANTES de /{id})
    Route::get('/emisores/{id}/validate-delete', [EmisorController::class, 'validateDelete']);
    Route::post('/emisores/{id}/prepare-deletion', [EmisorController::class, 'prepareDeletion']);
    Route::delete('/emisores/{id}/permanent', [EmisorController::class, 'destroyWithHistory']);
    
    // Usuarios asociados a un emisor
    Route::get('/emisores/{id}/usuarios', [UserController::class, 'indexByEmisor']);
    Route::post('/emisores/{id}/usuarios', [UserController::class, 'storeByEmisor']);
    Route::get('/emisores/{id}/usuarios/{usuario}', [UserController::class, 'showByEmisor']);
    Route::put('/emisores/{id}/usuarios/{usuario}', [UserController::class, 'updateByEmisor']);
    Route::delete('/emisores/{id}/usuarios/{usuario}', [UserController::class, 'destroyByEmisor']);
    Route::post('/emisores/{id}/usuarios/{usuario}/resend-verification', [UserController::class, 'resendVerificationEmailByEmisor']);
    
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

    // Puntos de Emisión asociados a un emisor (todos los puntos de todos sus establecimientos)
    Route::get('/emisores/{id}/puntos-emision', [PuntoEmisionController::class, 'listByEmisor']);
    
    // Rutas genéricas (DESPUÉS de todas las anidadas)
    Route::get('/emisores/{id}', [EmisorController::class, 'show']);
    Route::put('/emisores/{id}', [EmisorController::class, 'update']);
    Route::delete('/emisores/{id}', [EmisorController::class, 'destroy']);

    // Suscripciones de un emisor (Admin y Distribuidor)
    Route::get('/suscripciones/planes-activos', [SuscripcionController::class, 'planesActivos']);
    Route::get('/suscripciones/estados', [SuscripcionController::class, 'estados']);
    Route::post('/suscripciones/calcular-fecha-fin', [SuscripcionController::class, 'calcularFechaFin']);
    Route::get('/emisores/{emisorId}/suscripciones', [SuscripcionController::class, 'index']);
    Route::post('/emisores/{emisorId}/suscripciones', [SuscripcionController::class, 'store']);
    Route::get('/emisores/{emisorId}/suscripciones/{id}', [SuscripcionController::class, 'show']);
    Route::put('/emisores/{emisorId}/suscripciones/{id}', [SuscripcionController::class, 'update']);
    Route::delete('/emisores/{emisorId}/suscripciones/{id}', [SuscripcionController::class, 'destroy']);
    Route::get('/emisores/{emisorId}/suscripciones/{id}/campos-editables', [SuscripcionController::class, 'camposEditables']);
    
    // Gestión de estados de suscripción (HU9)
    Route::post('/emisores/{emisorId}/suscripciones/{id}/cambiar-estado', [SuscripcionController::class, 'cambiarEstado']);
    Route::get('/emisores/{emisorId}/suscripciones/{id}/transiciones-disponibles', [SuscripcionController::class, 'transicionesDisponibles']);
    Route::get('/emisores/{emisorId}/suscripciones/{id}/historial-estados', [SuscripcionController::class, 'historialEstados']);
    Route::post('/emisores/{emisorId}/suscripciones/evaluar-estados', [SuscripcionController::class, 'evaluarEstados']);
});


// Company logo routes
Route::get('/companies/{company}/logo', [LogoController::class, 'show']);
Route::get('/companies/{company}/logo-file', [LogoController::class, 'file'])->name('companies.logo.file');
Route::post('/companies/{company}/logo', [LogoController::class, 'store'])->middleware('auth:sanctum');

// Establecimiento logo routes
Route::get('/emisores/{id}/establecimientos/{est}/logo-file', [LogoController::class, 'establecimientos_file'])->name('establecimientos.logo.file');



