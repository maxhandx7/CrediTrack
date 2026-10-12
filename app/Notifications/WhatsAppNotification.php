<?php

namespace App\Notifications;

use App\Services\Waha\WahaChannel;
use App\Services\Waha\WahaClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;

/** Base de todos los avisos por WhatsApp: en cola, con reintentos. */
abstract class WhatsAppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 600];

    abstract public function toWaha(object $notifiable): string;

    /** Préstamo relacionado, para el historial de mensajes del cliente. */
    public function loanId(): ?int
    {
        return null;
    }

    public function via(object $notifiable): array
    {
        return app(WahaClient::class)->enabled() ? [WahaChannel::class] : [];
    }

    protected function money(float|string|null $amount): string
    {
        return Number::currency((float) $amount, in: 'COP', locale: 'es_CO', precision: 0);
    }

    protected function date($date): string
    {
        return $date->translatedFormat('l j \d\e F');
    }
}
