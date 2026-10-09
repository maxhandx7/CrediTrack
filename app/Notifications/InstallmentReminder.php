<?php

namespace App\Notifications;

use App\Models\LoanSchedule;
use App\Services\MessageTemplates;

class InstallmentReminder extends WhatsAppNotification
{
    public function __construct(public LoanSchedule $schedule) {}

    public function loanId(): ?int
    {
        return $this->schedule->loan_id;
    }

    public function toWaha(object $notifiable): string
    {
        $s = $this->schedule->loadMissing('loan.user');

        return MessageTemplates::render($s->loan->user, 'reminder', [
            'nombre' => $notifiable->firstName(),
            'monto' => $this->money($s->amount_pending),
            'fecha' => $s->scheduled_date->isToday() ? '*hoy*' : 'el '.$this->date($s->scheduled_date),
            'saldo' => $this->money($s->loan->balance),
            'enlace' => $s->loan->statementUrl(),
        ]);
    }
}
