<?php

namespace App\Notifications;

use App\Enums\LoanStatus;
use App\Models\Payment;

class PaymentReceived extends WhatsAppNotification
{
    public function __construct(public Payment $payment) {}

    public function toWaha(object $notifiable): string
    {
        $loan = $this->payment->loan()->with(['user', 'schedules'])->first();
        $next = $loan->schedules->first(fn ($s) => $s->amount_pending > 0);

        $text = "✅ Pago recibido, {$notifiable->firstName()}.\n\n"
            ."Abono: *{$this->money($this->payment->amount)}* ({$this->payment->date->format('d/m/Y')})\n"
            ."Saldo pendiente: *{$this->money($loan->balance)}*\n";

        if ($loan->status === LoanStatus::Paid) {
            $text .= "\n🎉 ¡Terminaste de pagar tu préstamo! Gracias por tu cumplimiento.\n";
        } elseif ($next) {
            $text .= "Próxima cuota: {$this->money($next->amount_pending)} el {$this->date($next->scheduled_date)}.\n";
        }

        return $text."\n— {$loan->user->name}";
    }
}
