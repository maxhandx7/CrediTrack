<?php

use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\PublicDocumentController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('landing');

// ── Portal de clientes (/mi-cuenta) ─────────────────────────
Route::prefix('mi-cuenta')->name('portal.')->group(function () {
    Route::get('/ingresar', [PortalController::class, 'showLogin'])->name('login');
    Route::post('/ingresar', [PortalController::class, 'requestCode'])->middleware('throttle:client-code')->name('code');
    Route::get('/codigo', [PortalController::class, 'showVerify'])->name('verify');
    Route::post('/codigo', [PortalController::class, 'verify'])->middleware('throttle:client-code')->name('verify.submit');

    Route::middleware('auth:client')->group(function () {
        Route::get('/', [PortalController::class, 'home'])->name('home');
        Route::post('/salir', [PortalController::class, 'logout'])->name('logout');
    });
});

// ── Documentos que reciben los clientes por WhatsApp ────────
Route::middleware('throttle:30,1')->where(['token' => '[A-Za-z0-9]{40}'])->group(function () {
    Route::get('/recibo/{token}', [PublicDocumentController::class, 'receipt'])->name('public.receipt');
    Route::get('/estado/{token}', [PublicDocumentController::class, 'statement'])->name('public.statement');
});

// Compatibilidad con el login viejo de la app React
Route::permanentRedirect('/login-client', '/mi-cuenta/ingresar');
Route::permanentRedirect('/login', '/admin/login');
