<?php

namespace App\Console\Commands;

use App\Services\CollectionPlanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunCollections extends Command
{
    protected $signature = 'creditrack:collections {--dry-run : Muestra qué se enviaría sin enviarlo} {--all-hours : Ignora la hora de envío de cada empresa}';

    protected $description = 'Actualiza cuotas vencidas y envía recordatorios de cobro por WhatsApp';

    public function handle(CollectionPlanner $planner): int
    {
        // En ensayo no se guarda NADA: ni estados recalculados ni avisos marcados como enviados
        // (si no, los recordatorios reales de ese día se saltarían).
        if ($this->option('dry-run')) {
            DB::beginTransaction();
            try {
                $this->report($planner->plan($this->hour()), send: false);
            } finally {
                DB::rollBack();
            }

            return self::SUCCESS;
        }

        $this->report($planner->plan($this->hour()), send: true);

        return self::SUCCESS;
    }

    private function hour(): ?int
    {
        return $this->option('all-hours') ? null : (int) now()->format('G');
    }

    private function report(array $messages, bool $send): void
    {
        $spacing = (int) config('services.waha.spacing', 12);

        foreach ($messages as $i => [$notifiable, $notification]) {
            $this->line(sprintf('• %-28s %s', class_basename($notification), $notifiable->name));

            if ($send) {
                // Mensajes espaciados: un número que envía 50 mensajes en 1 segundo es bloqueado.
                $notifiable->notify($notification->delay(now()->addSeconds($i * $spacing)));
            }
        }

        $this->info(count($messages).' aviso(s) '.($send ? 'en cola.' : 'se enviarían (ensayo: no se guardó nada).'));
    }
}
