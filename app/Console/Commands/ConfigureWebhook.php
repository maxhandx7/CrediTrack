<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ConfigureWebhook extends Command
{
    protected $signature = 'creditrack:webhook {email : Correo del prestamista} {url : URL que recibe los eventos}';

    protected $description = 'Conecta un prestamista con un sistema externo (ej. https://afdeveloper.com/webhooks/creditrack)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No existe ese usuario.');

            return self::FAILURE;
        }

        $secret = Str::random(48);
        $user->forceFill(['webhook_url' => $this->argument('url'), 'webhook_secret' => $secret])->save();

        $this->info("Webhook configurado para {$user->name}.");
        $this->line('Pon este secreto en el .env del sistema que recibe (CREDITRACK_WEBHOOK_SECRET):');
        $this->newLine();
        $this->line("  {$secret}");
        $this->newLine();
        $this->warn('No se vuelve a mostrar. Si lo pierdes, ejecuta el comando de nuevo.');

        return self::SUCCESS;
    }
}
