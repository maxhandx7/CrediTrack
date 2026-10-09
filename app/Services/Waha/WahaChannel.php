<?php

namespace App\Services\Waha;

use App\Models\Client;
use App\Models\MessageLog;
use Illuminate\Notifications\Notification;
use Throwable;

/** Canal de notificaciones por WhatsApp (WAHA). Lo enviado a clientes queda en su historial. */
class WahaChannel
{
    public function __construct(private WahaClient $client) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->routeNotificationFor('waha', $notification);

        if (blank($phone) || ! method_exists($notification, 'toWaha')) {
            return;
        }

        $body = $notification->toWaha($notifiable);

        try {
            $this->client->sendText($phone, $body);
            $this->log($notifiable, $notification, $body, 'sent');
        } catch (Throwable $e) {
            $this->log($notifiable, $notification, $body, 'failed', $e->getMessage());
            throw $e; // la cola lo reintenta
        }
    }

    private function log(object $notifiable, Notification $notification, string $body, string $status, ?string $error = null): void
    {
        if (! $notifiable instanceof Client) {
            return;
        }

        MessageLog::create([
            'user_id' => $notifiable->user_id,
            'client_id' => $notifiable->id,
            'loan_id' => method_exists($notification, 'loanId') ? $notification->loanId() : null,
            'type' => class_basename($notification),
            'body' => $body,
            'status' => $status,
            'error' => $error ? mb_substr($error, 0, 250) : null,
        ]);
    }
}
