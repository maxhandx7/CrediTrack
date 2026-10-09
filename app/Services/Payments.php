<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\PromiseStatus;
use App\Models\Loan;
use App\Models\Payment;
use App\Notifications\PaymentReceived;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Registrar y anular pagos: un solo lugar para el libro contable, webhooks y avisos. */
class Payments
{
    public function __construct(private LoanLedger $ledger, private WebhookDispatcher $webhooks) {}

    public function register(Loan $loan, array $data, bool $notify = true): Payment
    {
        $loan->loadMissing('client');

        if ($loan->status === LoanStatus::Cancelled) {
            throw new InvalidArgumentException('El préstamo está cancelado.');
        }
        if ((float) $data['amount'] <= 0 || (float) $data['amount'] > $loan->balance + 0.01) {
            throw new InvalidArgumentException('El pago debe ser mayor a 0 y no superar el saldo ($'.number_format($loan->balance, 0, ',', '.').').');
        }

        $payment = DB::transaction(function () use ($loan, $data) {
            $payment = $loan->payments()->create([
                'user_id' => $loan->user_id,
                'amount' => $data['amount'],
                'date' => $data['date'] ?? today(),
                'method' => $data['method'] ?? 'efectivo',
                'reference' => $data['reference'] ?? null,
                'attachment' => $data['attachment'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $this->ledger->recalculate($loan);
            $this->resolvePromises($loan);

            return $payment->fresh();
        });

        $this->webhooks->paymentCreated($payment);

        if ($notify && $loan->client->routeNotificationForWaha()) {
            $loan->client->notify(new PaymentReceived($payment));
        }

        return $payment;
    }

    /** Anular deja el pago en el historial (con motivo) pero deja de contar. */
    public function void(Payment $payment, string $reason): Payment
    {
        $payment->loadMissing('loan');

        DB::transaction(function () use ($payment, $reason) {
            $payment->update(['voided_at' => now(), 'void_reason' => $reason]);
            $this->ledger->recalculate($payment->loan);
        });

        $this->webhooks->paymentDeleted($payment);

        return $payment;
    }

    /** Si con este pago se cubrió lo prometido, la promesa queda cumplida. */
    private function resolvePromises(Loan $loan): void
    {
        $loan->promises()->where('status', PromiseStatus::Pending)->get()->each(function ($promise) {
            if ($promise->paidSoFar() >= (float) $promise->amount - 0.01) {
                $promise->update(['status' => PromiseStatus::Kept, 'resolved_at' => now()]);
            }
        });
    }
}
