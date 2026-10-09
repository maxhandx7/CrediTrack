<?php

namespace App\Notifications;

/** Resumen de la mañana para el prestamista. */
class LenderDailyDigest extends WhatsAppNotification
{
    /** @param array{due_today_count:int, due_today_total:float, overdue_count:int, overdue_total:float, overdue_clients:list<string>} $summary */
    public function __construct(public array $summary) {}

    public function toWaha(object $notifiable): string
    {
        $s = $this->summary;
        $text = "☀️ Buenos días, ".strtok($notifiable->name, ' ').". Así va tu cartera hoy:\n\n"
            ."• Vencen hoy: {$s['due_today_count']} cuota(s) por *{$this->money($s['due_today_total'])}*\n"
            ."• Vencidas: {$s['overdue_count']} cuota(s) por *{$this->money($s['overdue_total'])}*\n";

        if ($s['overdue_clients'] !== []) {
            $text .= "\nCon atraso: ".implode(', ', array_slice($s['overdue_clients'], 0, 8))
                .(count($s['overdue_clients']) > 8 ? ' y más.' : '.')."\n";
        }

        if (! empty($s['broken_promises'])) {
            $text .= "\n⚠️ Incumplieron su promesa de pago: ".implode(', ', $s['broken_promises']).".\n";
        }

        return $text."\nLos clientes ya recibieron su recordatorio automático.";
    }
}
