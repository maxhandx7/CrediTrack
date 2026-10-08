<?php

namespace App\Notifications;

use App\Models\LoanSchedule;

class InstallmentReminder extends WhatsAppNotification
{
    public function __construct(public LoanSchedule $schedule) {}

    public function toWaha(object $notifiable): string
    {
        $s = $this->schedule->loadMissing('loan.user');
        $when = $s->scheduled_date->isToday() ? '*hoy*' : 'el '.$this->date($s->scheduled_date);

        return "Hola {$notifiable->firstName()} 👋\n\n"
            ."Te recuerdo que {$when} vence tu cuota de *{$this->money($s->amount_pending)}*.\n\n"
            ."Saldo total del préstamo: {$this->money($s->loan->balance)}.\n\n"
            ."Si ya pagaste, ignora este mensaje. Gracias 🙏\n— {$s->loan->user->name}";
    }
}
