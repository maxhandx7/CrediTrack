<?php

namespace App\Notifications;

use App\Enums\LoanStatus;
use App\Models\Payment;
use App\Services\MessageTemplates;

class PaymentReceived extends WhatsAppNotification
{
    public function __construct(public Payment $payment) {}

    public function loanId(): ?int
    {
        return $this->payment->loan_id;
    }

    public function toWaha(object $notifiable): string
    {
        $loan = $this->payment->loan()->with(['user', 'schedules'])->first();

        $text = MessageTemplates::render($loan->user, 'receipt', [
            'nombre' => $notifiable->firstName(),
            'monto' => $this->money($this->payment->amount),
            'fecha' => $this->payment->date->format('d/m/Y'),
            'saldo' => $this->money($loan->balance),
            'enlace' => $this->payment->receiptUrl(),
        ]);

        if ($loan->status === LoanStatus::Paid) {
            $text .= "\n\n🎉 ¡Terminaste de pagar tu préstamo! Gracias por tu cumplimiento.";
        } elseif ($next = $loan->nextSchedule()) {
            $text .= "\n\nPróxima cuota: {$this->money($next->amount_pending)} el {$this->date($next->scheduled_date)}.";
        }

        return $text;
    }
}
