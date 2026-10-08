<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserAuthController extends Controller
{
    public function register(Request $request)
    {
        abort_unless(config('creditrack.registration_enabled'), 403, 'El registro está cerrado. Pide acceso al administrador.');

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
        ]);

        $user = User::create($data);

        return response()->json([
            'token' => $user->createToken('user_token', ['lender'])->plainTextToken,
            'user' => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        if (! $user->isActive()) {
            return response()->json(['message' => 'Usuario inactivo. Contacta al administrador.'], 403);
        }

        $user->tokens()->delete();

        return response()->json([
            'token' => $user->createToken('user_token', ['lender'])->plainTextToken,
            'user' => $user,
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }
}
