<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::where('user_id', Auth::id())->get();
        return response()->json($clients);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'document' => 'required|string|max:20|unique:clients',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $client = Client::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'document' => $request->document,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'notes' => $request->notes,
        ]);

        return response()->json(['message' => 'Cliente creado correctamente', 'client' => $client], 201);
    }

    public function show($id)
    {
        $client = Client::where('user_id', Auth::id())->findOrFail($id);
        return response()->json($client);
    }

    public function update(Request $request, $id)
    {
        $client = Client::where('user_id', Auth::id())->findOrFail($id);

        $client->update($request->only(['name', 'email', 'phone', 'address', 'notes']));

        return response()->json(['message' => 'Cliente actualizado correctamente', 'client' => $client]);
    }

    public function destroy($id)
    {
        $client = Client::where('user_id', Auth::id())->findOrFail($id);
        $client->delete();
        return response()->json(['message' => 'Cliente eliminado correctamente']);
    }
}