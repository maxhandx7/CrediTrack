<?php
namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoanController extends Controller
{
    public function index()
    {
        $loans = Loan::where('user_id', Auth::id())
            ->with('client')
            ->latest()
            ->get();
        return response()->json($loans);
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'amount' => 'required|numeric|min:1',
            'interest_rate' => 'required|numeric|min:0',
            'interest_type' => 'required|in:simple,compuesto',
            'start_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:start_date',
            'payment_frequency' => 'required|in:diaria,semanal,quincenal,mensual',
            'notes' => 'nullable|string',
        ]);

        $loan = Loan::create([
            'user_id' => Auth::id(),
            'client_id' => $request->client_id,
            'amount' => $request->amount,
            'interest_rate' => $request->interest_rate,
            'interest_type' => $request->interest_type,
            'start_date' => $request->start_date,
            'due_date' => $request->due_date,
            'payment_frequency' => $request->payment_frequency,
            'status' => 'activo',
            'notes' => $request->notes,
        ]);

        return response()->json(['message' => 'Préstamo registrado correctamente', 'loan' => $loan], 201);
    }

    public function show($id)
    {
        $loan = Loan::where('user_id', Auth::id())
            ->with(['client', 'payments'])
            ->findOrFail($id);
        return response()->json($loan);
    }

    public function update(Request $request, $id)
    {
        $loan = Loan::where('user_id', Auth::id())->findOrFail($id);

        $loan->update($request->only(['amount', 'interest_rate', 'interest_type', 'due_date', 'payment_frequency', 'status', 'notes']));

        return response()->json(['message' => 'Préstamo actualizado correctamente', 'loan' => $loan]);
    }

    public function destroy($id)
    {
        $loan = Loan::where('user_id', Auth::id())->findOrFail($id);
        $loan->delete();
        return response()->json(['message' => 'Préstamo eliminado correctamente']);
    }
}