<?php

namespace App\Services;

use App\Enums\InterestType;
use App\Models\Loan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Genera el cronograma de cuotas de un préstamo.
 *
 * - La tasa es POR PERIODO (ej. 5 % mensual en un préstamo mensual).
 * - La primera cuota cae un periodo después del desembolso y la última en la
 *   fecha final (antes la primera caía el mismo día del desembolso y la última
 *   nunca llegaba a la fecha final).
 * - Los centavos que sobran del redondeo van en la última cuota.
 */
class ScheduleGenerator
{
    /** @return array{total: float, installments: list<array{date: string, amount: float}>} */
    public function plan(float $principal, float $ratePercent, InterestType $type, $frequency, string $start, string $end): array
    {
        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->startOfDay();
        $periods = $frequency->periodsBetween($startDate, $endDate);
        $rate = $ratePercent / 100;

        $total = $type === InterestType::Simple
            ? $principal * (1 + $rate * $periods)
            : $principal * (1 + $rate) ** $periods;
        $total = round($total, 2);

        $base = floor($total / $periods * 100) / 100;
        $installments = [];

        for ($n = 1; $n <= $periods; $n++) {
            $date = $frequency->nth($startDate, $n);
            if ($date->gt($endDate) || $n === $periods) {
                $date = $endDate->copy();
            }

            $installments[] = [
                'date' => $date->toDateString(),
                'amount' => $n === $periods ? round($total - $base * ($periods - 1), 2) : $base,
            ];
        }

        return ['total' => $total, 'installments' => $installments];
    }

    public function generate(Loan $loan): void
    {
        $plan = $this->plan(
            (float) $loan->amount,
            (float) $loan->interest_rate,
            $loan->interest_type,
            $loan->payment_frequency,
            $loan->start_date->toDateString(),
            $loan->due_date->toDateString(),
        );

        DB::transaction(function () use ($loan, $plan) {
            $loan->schedules()->delete();
            $loan->schedules()->createMany(array_map(fn ($i) => [
                'scheduled_date' => $i['date'],
                'amount_due' => $i['amount'],
            ], $plan['installments']));

            $loan->forceFill([
                'total_amount' => $plan['total'],
                'installments_count' => count($plan['installments']),
            ])->save();
        });
    }
}
