<?php

namespace App\Notifications;

/** Código de acceso al portal. Se envía con notifyNow(): el cliente lo está esperando. */
class ClientAccessCode extends WhatsAppNotification
{
    public int $tries = 1;

    public function __construct(public string $code) {}

    public function toWaha(object $notifiable): string
    {
        return "Tu código para entrar a CrediTrack es: *{$this->code}*\n\n"
            .'Vence en '.config('creditrack.login_code_minutes')." minutos. No lo compartas con nadie.";
    }
}
