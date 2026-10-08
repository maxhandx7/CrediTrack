<?php

namespace App\Notifications;

use App\Models\LoanSchedule;

class InstallmentOverdue extends WhatsAppNotification
{
    public function __construct(public LoanSchedule $schedule) {}

    public function toWaha(object $notifiable): string
    {
        $s = $this->schedule->loadMissing('loan.user');
        $days = $s->daysOverdue();

        return "Hola {$notifiable->firstName()}.\n\n"
            ."Tu cuota del {$this->date($s->scheduled_date)} por *{$this->money($s->amount_pending)}* "
            ."tiene {$days} ".($days === 1 ? 'día' : 'días')." de atraso.\n\n"
            ."¿Me confirmas cuándo puedes ponerte al día? Si ya pagaste, envíame el comprobante por aquí.\n"
            ."— {$s->loan->user->name}";
    }
}
