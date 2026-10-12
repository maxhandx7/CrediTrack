<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;

class CheckSubscriptions extends Command
{
    protected $signature = 'saas:subscriptions';

    protected $description = 'Marca como vencidas las empresas cuya prueba o suscripción expiró';

    public function handle(): int
    {
        $n = 0;
        Company::whereIn('status', ['trial', 'active'])->each(function (Company $c) use (&$n) {
            if (! $c->hasAccess()) {
                $c->update(['status' => 'past_due']);
                $this->line("• {$c->name}: vencida");
                $n++;
            }
        });
        $this->info("{$n} empresa(s) marcada(s) como vencidas.");

        return self::SUCCESS;
    }
}
