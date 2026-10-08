<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Auth\ClientAuthController;
use App\Http\Controllers\Auth\UserAuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanScheduleController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Portal\ClientPortalController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

// ── Autenticación ───────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::middleware('throttle:login')->group(function () {
        Route::post('/user/login', [UserAuthController::class, 'login']);
        Route::post('/user/register', [UserAuthController::class, 'register']);
    });
    Route::middleware('throttle:client-code')->group(function () {
        Route::post('/client/request-code', [ClientAuthController::class, 'requestCode']);
        Route::post('/client/verify', [ClientAuthController::class, 'verify']);
    });

    Route::middleware(['auth:sanctum', 'lender'])->group(function () {
        Route::get('/user/me', [UserAuthController::class, 'profile']);
        Route::post('/user/logout', [UserAuthController::class, 'logout']);
    });
    Route::post('/client/logout', [ClientAuthController::class, 'logout'])->middleware(['auth:sanctum', 'client']);
});

// ── Prestamista ─────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'lender'])->group(function () {
    Route::apiResource('clients', ClientController::class);
    Route::apiResource('loans', LoanController::class);
    Route::apiResource('payments', PaymentController::class)->only(['index', 'store', 'destroy']);
    Route::apiResource('schedules', LoanScheduleController::class);
    Route::get('analytics/export', [AnalyticsController::class, 'export']);
    Route::get('settings', [SettingsController::class, 'show']);
    Route::put('settings', [SettingsController::class, 'update']);
});

// ── Portal del cliente (deudor) ─────────────────────────────
Route::middleware(['auth:sanctum', 'client'])->prefix('client')->group(function () {
    Route::get('/me', [ClientPortalController::class, 'me']);
    Route::get('/loans', [ClientPortalController::class, 'loans']);
});
