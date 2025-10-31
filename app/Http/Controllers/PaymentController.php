<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function index()
{
    $payments = Payment::where('user_id', Auth::id())
        ->with('loan.client')
        ->latest()
        ->get();

    return response()->json($payments);
}

    public function store(Request $request)
    {
        $request->validate([
            'loan_id' => 'required|exists:loans,id',
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $loan = Loan::where('user_id', Auth::id())->findOrFail($request->loan_id);

        $remaining = max(0, $loan->amount - ($loan->payments()->sum('amount') + $request->amount));

        $payment = Payment::create([
            'loan_id' => $loan->id,
            'user_id' => Auth::id(),
            'amount' => $request->amount,
            'date' => $request->date,
            'remaining_balance' => $remaining,
            'notes' => $request->notes,
        ]);

        // Si ya se pagó todo, marcar préstamo como "pagado"
        if ($remaining <= 0) {
            $loan->update(['status' => 'pagado']);
        }

        return response()->json(['message' => 'Pago registrado correctamente', 'payment' => $payment]);
    }

    public function destroy($id)
    {
        $payment = Payment::where('user_id', Auth::id())->findOrFail($id);
        $payment->delete();
        return response()->json(['message' => 'Pago eliminado correctamente']);
    }
}
