<?php


use App\Http\Controllers\Auth\ClientAuthController;
use App\Http\Controllers\Auth\UserAuthController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanScheduleController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;




Route::prefix('auth')->group(function () {
    // Prestamista
    Route::post('/user/login', [UserAuthController::class, 'login']);
    Route::post('/user/logout', [UserAuthController::class, 'logout'])->middleware('auth:sanctum');

    // Cliente
    Route::post('/client/login', [ClientAuthController::class, 'login']);
    Route::post('/client/logout', [ClientAuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('clients', \App\Http\Controllers\ClientController::class);
    Route::apiResource('loans', LoanController::class);
    Route::apiResource('payments', PaymentController::class)->only(['store', 'destroy']);
    Route::apiResource('schedules', LoanScheduleController::class);
});
