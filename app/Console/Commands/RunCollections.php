<?php

namespace App\Console\Commands;

use App\Services\CollectionPlanner;
use Illuminate\Console\Command;

class RunCollections extends Command
{
    protected $signature = 'creditrack:collections {--dry-run : Muestra qué se enviaría sin enviarlo}';

    protected $description = 'Actualiza cuotas vencidas y envía recordatorios de cobro por WhatsApp';

    public function handle(CollectionPlanner $planner): int
    {
        $messages = $planner->plan();
        $spacing = (int) config('services.waha.spacing', 12);

        foreach ($messages as $i => [$notifiable, $notification]) {
            $this->line(sprintf('• %-28s %s', class_basename($notification), $notifiable->name));

            if (! $this->option('dry-run')) {
                // Mensajes espaciados: un número que envía 50 mensajes en 1 segundo es bloqueado.
                $notifiable->notify($notification->delay(now()->addSeconds($i * $spacing)));
            }
        }

        $this->info(count($messages).' aviso(s) '.($this->option('dry-run') ? 'por enviar.' : 'en cola.'));

        return self::SUCCESS;
    }
}
