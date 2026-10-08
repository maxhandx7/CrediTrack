<?php

namespace App\Services\Webhooks;

use App\Jobs\SendWebhook;
use App\Models\Loan;
use App\Models\Payment;
use Illuminate\Support\Str;

/** Arma los eventos que CrediTrack envía a sistemas externos. */
class WebhookDispatcher
{
    public function loanCreated(Loan $loan): void
    {
        $this->send($loan->user_id, 'loan.created', $this->loanData($loan));
    }

    public function loanDeleted(Loan $loan): void
    {
        $this->send($loan->user_id, 'loan.deleted', ['id' => $loan->id]);
    }

    public function paymentCreated(Payment $payment): void
    {
        $payment->loadMissing('loan.client');

        $this->send($payment->user_id, 'payment.created', [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'date' => $payment->date->toDateString(),
            'remaining_balance' => (float) $payment->remaining_balance,
            'notes' => $payment->notes,
            'loan' => $this->loanData($payment->loan),
        ]);
    }

    public function paymentDeleted(Payment $payment): void
    {
        $this->send($payment->user_id, 'payment.deleted', ['id' => $payment->id, 'loan_id' => $payment->loan_id]);
    }

    private function loanData(Loan $loan): array
    {
        $loan->loadMissing('client');

        return [
            'id' => $loan->id,
            'amount' => (float) $loan->amount,
            'total_amount' => (float) $loan->total_amount,
            'start_date' => $loan->start_date->toDateString(),
            'due_date' => $loan->due_date->toDateString(),
            'client' => [
                'name' => $loan->client->name,
                'document' => $loan->client->document,
                'phone' => $loan->client->phone,
                'email' => $loan->client->email,
            ],
        ];
    }

    private function send(int $userId, string $event, array $data): void
    {
        SendWebhook::dispatch($userId, [
            'id' => (string) Str::uuid(),
            'event' => $event,
            'created_at' => now()->toIso8601String(),
            'data' => $data,
        ])->afterCommit();
    }
}
