<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'document' => 'required|string|exists:clients,document',
        ]);

        $client = Client::where('document', $request->document)->first();

        if (! $client) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }
        $client->tokens()->delete();
        $token = $client->createToken('client_token')->plainTextToken;

        return response()->json(['token' => $token, 'client' => $client]);
    }

    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }
}
