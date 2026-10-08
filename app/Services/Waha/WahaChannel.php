<?php

namespace App\Services\Waha;

use Illuminate\Notifications\Notification;

/** Canal de notificaciones de Laravel que envía por WhatsApp usando WAHA. */
class WahaChannel
{
    public function __construct(private WahaClient $client) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->routeNotificationFor('waha', $notification);

        if (blank($phone) || ! method_exists($notification, 'toWaha')) {
            return;
        }

        $this->client->sendText($phone, $notification->toWaha($notifiable));
    }
}
