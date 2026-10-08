<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Notifications\PaymentReceived;
use App\Services\LoanLedger;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(private LoanLedger $ledger, private WebhookDispatcher $webhooks) {}

    public function index(Request $request)
    {
        return response()->json($request->user()->payments()->with('loan.client')->latest('date')->latest('id')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'loan_id' => 'required|integer',
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string',
            'notify' => 'sometimes|boolean',
        ]);

        $loan = $request->user()->loans()->findOrFail($data['loan_id']);

        if ($loan->status->value === 'cancelado') {
            return response()->json(['message' => 'El préstamo está cancelado.'], 422);
        }
        if ((float) $data['amount'] > $loan->balance + 0.01) {
            return response()->json([
                'message' => 'El pago supera el saldo pendiente ('.number_format($loan->balance, 0, ',', '.').').',
            ], 422);
        }

        $payment = DB::transaction(function () use ($request, $loan, $data) {
            $payment = $loan->payments()->create([
                'user_id' => $request->user()->id,
                'amount' => $data['amount'],
                'date' => $data['date'],
                'notes' => $data['notes'] ?? null,
            ]);
            $this->ledger->recalculate($loan);

            return $payment->fresh();
        });

        $this->webhooks->paymentCreated($payment);

        if (($data['notify'] ?? true) && $loan->client->routeNotificationForWaha()) {
            $loan->client->notify(new PaymentReceived($payment));
        }

        return response()->json(['message' => 'Pago registrado correctamente', 'payment' => $payment, 'loan' => $loan->fresh()]);
    }

    public function destroy(Request $request, int $id)
    {
        $payment = $request->user()->payments()->findOrFail($id);

        DB::transaction(function () use ($payment) {
            $payment->delete();
            $this->ledger->recalculate($payment->loan);
        });

        $this->webhooks->paymentDeleted($payment);

        return response()->json(['message' => 'Pago eliminado correctamente']);
    }
}
