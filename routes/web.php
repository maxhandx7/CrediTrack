<?php

use Illuminate\Support\Facades\Route;

// La SPA de React maneja todas las rutas del navegador.
Route::view('/{any?}', 'welcome')->where('any', '^(?!api).*$');
