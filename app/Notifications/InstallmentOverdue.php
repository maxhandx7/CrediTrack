<?php

namespace App\Notifications;

use App\Models\LoanSchedule;
use App\Services\MessageTemplates;

class InstallmentOverdue extends WhatsAppNotification
{
    public function __construct(public LoanSchedule $schedule) {}

    public function loanId(): ?int
    {
        return $this->schedule->loan_id;
    }

    public function toWaha(object $notifiable): string
    {
        $s = $this->schedule->loadMissing('loan.user');

        return MessageTemplates::render($s->loan->user, 'overdue', [
            'nombre' => $notifiable->firstName(),
            'monto' => $this->money($s->amount_pending),
            'fecha' => 'el '.$this->date($s->scheduled_date),
            'saldo' => $this->money($s->loan->balance),
            'dias' => $s->daysOverdue(),
            'enlace' => $s->loan->statementUrl(),
        ]);
    }
}
