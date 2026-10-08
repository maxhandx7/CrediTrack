<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/**
 * Solo prestamistas activos con token de prestamista.
 * Antes, el token de un CLIENTE pasaba por las mismas rutas y Auth::id()
 * devolvía su id: el cliente #3 veía todo lo del prestamista #3.
 */
class EnsureLender
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->tokenCan('lender')) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if (! $user->isActive()) {
            return response()->json(['message' => 'Usuario inactivo. Contacta al administrador.'], 403);
        }

        return $next($request);
    }
}
