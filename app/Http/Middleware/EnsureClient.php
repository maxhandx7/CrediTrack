<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;

class EnsureClient
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() instanceof Client || ! $request->user()->tokenCan('client')) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        return $next($request);
    }
}
