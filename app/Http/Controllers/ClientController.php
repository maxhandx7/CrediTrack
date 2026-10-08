<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        return response()->json($request->user()->clients()->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $client = $request->user()->clients()->create($this->validated($request));

        return response()->json(['message' => 'Cliente creado correctamente', 'client' => $client], 201);
    }

    public function show(Request $request, int $id)
    {
        return response()->json($request->user()->clients()->findOrFail($id));
    }

    public function update(Request $request, int $id)
    {
        $client = $request->user()->clients()->findOrFail($id);
        $client->update($this->validated($request, $client->id, partial: true));

        return response()->json(['message' => 'Cliente actualizado correctamente', 'client' => $client]);
    }

    public function destroy(Request $request, int $id)
    {
        $client = $request->user()->clients()->findOrFail($id);

        if ($client->loans()->whereIn('status', ['activo', 'atrasado'])->exists()) {
            return response()->json(['message' => 'El cliente tiene préstamos activos. Ciérralos antes de eliminarlo.'], 422);
        }

        $client->delete();

        return response()->json(['message' => 'Cliente eliminado correctamente']);
    }

    private function validated(Request $request, ?int $ignoreId = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:100'],
            'document' => [$required, 'string', 'max:20',
                Rule::unique('clients')->where('user_id', $request->user()->id)->ignore($ignoreId)],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp_opt_in' => ['sometimes', 'boolean'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
