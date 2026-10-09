<?php

namespace App\Notifications;

use App\Models\PaymentPromise;
use App\Services\MessageTemplates;

class PromiseReminder extends WhatsAppNotification
{
    public function __construct(public PaymentPromise $promise) {}

    public function loanId(): ?int
    {
        return $this->promise->loan_id;
    }

    public function toWaha(object $notifiable): string
    {
        $p = $this->promise->loadMissing('loan.user');

        return MessageTemplates::render($p->loan->user, 'promise', [
            'nombre' => $notifiable->firstName(),
            'monto' => $this->money($p->amount),
            'fecha' => $this->date($p->promised_date),
            'saldo' => $this->money($p->loan->balance),
            'enlace' => $p->loan->statementUrl(),
        ]);
    }
}
