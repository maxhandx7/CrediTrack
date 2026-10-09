<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanLedger;
use App\Services\ScheduleGenerator;
use Illuminate\Database\Seeder;

/**
 * Datos de prueba: un prestamista, clientes y préstamos en distintos estados.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Es seguro correrlo varias veces: borra y recrea solo los datos del usuario demo.
 */
class DemoSeeder extends Seeder
{
    public function run(ScheduleGenerator $generator, LoanLedger $ledger): void
    {
        if (app()->isProduction()) {
            $this->command->error('Este seeder es solo para desarrollo. No se ejecutó.');

            return;
        }

        $lender = User::updateOrCreate(['email' => 'demo@creditrack.test'], [
            'name' => 'Alan Demo',
            'password' => 'password',
            'phone' => '3000000000',
            'settings' => ['business_name' => 'Créditos Demo', 'late_fee' => ['enabled' => true, 'percent' => 5, 'grace_days' => 3]],
        ]);
        $lender->forceFill(['status' => 'active', 'role' => 'admin'])->save();
        $lender->clients()->delete(); // cascada: préstamos, cuotas y pagos del demo

        $clients = collect([
            ['name' => 'Pedro Gómez', 'document' => '1001', 'phone' => '3001111111'],
            ['name' => 'Ana Díaz', 'document' => '1002', 'phone' => '3002222222'],
            ['name' => 'Luis Martínez', 'document' => '1003', 'phone' => '3003333333'],
        ])->map(fn ($c) => $lender->clients()->create($c));

        $loan = function (Client $client, int $amount, string $start, string $end, string $frequency, array $payments = []) use ($lender, $generator, $ledger) {
            $loan = $lender->loans()->create([
                'client_id' => $client->id, 'amount' => $amount, 'interest_rate' => 10,
                'interest_type' => 'simple', 'start_date' => $start, 'due_date' => $end,
                'payment_frequency' => $frequency,
            ]);
            $generator->generate($loan);

            foreach ($payments as [$value, $date]) {
                $loan->payments()->create(['user_id' => $lender->id, 'amount' => $value, 'date' => $date]);
            }

            return $ledger->recalculate($loan);
        };

        $d = fn (string $modify) => today()->modify($modify)->format('Y-m-d');

        // Al día: empezó hace un mes y ya pagó la primera cuota.
        $loan($clients[0], 1000000, $d('-1 month'), $d('+2 months'), 'mensual', [[433333.33, $d('-2 days')]]);
        // Atrasado: dos cuotas semanales vencidas sin pagar.
        $loan($clients[1], 500000, $d('-3 weeks'), $d('+3 weeks'), 'semanal');
        // Pagado completo.
        $loan($clients[2], 300000, $d('-2 months'), $d('-1 day'), 'mensual', [[180000, $d('-1 month')], [180000, $d('-3 days')]]);
        // Vence mañana: para probar el recordatorio con --dry-run.
        $loan($clients[2], 200000, $d('-1 day'), $d('+1 day'), 'diaria');


        \App\Models\PaymentPromise::create(['user_id' => $lender->id, 'loan_id' => $clients[1]->loans()->first()->id,
            'promised_date' => today()->addDays(2), 'amount' => 100000, 'notes' => 'Le pagan el viernes']);

        $this->command->info('Panel → /admin  ·  demo@creditrack.test  /  password');
        $this->command->newLine();
        $this->command->info('Portal de clientes → /mi-cuenta (cédula 1001, código por WhatsApp).');
        $this->command->newLine();
        $this->command->info('Recordatorios que saldrían hoy: php artisan creditrack:collections --dry-run');
    }
}
