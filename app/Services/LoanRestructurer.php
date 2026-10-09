<?php

namespace App\Services;

use App\Enums\PaymentFrequency;
use App\Enums\ScheduleStatus;
use App\Models\Loan;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reorganiza lo que falta por pagar (lo que hicimos a mano con Andrés):
 *  - Las cuotas pagadas se conservan tal cual (historial intacto).
 *  - Una cuota con abono parcial se "cierra" por lo abonado.
 *  - El saldo pendiente (+ interés adicional, si se acuerda) se reparte en cuotas
 *    nuevas del valor y frecuencia acordados, empezando en la fecha indicada.
 */
class LoanRestructurer
{
    public function __construct(private LoanLedger $ledger) {}

    /** @return list<array{date: string, amount: float}> */
    public function preview(Loan $loan, CarbonInterface $firstDate, PaymentFrequency $frequency, float $installment, float $extra = 0): array
    {
        $remaining = round($loan->balance + $extra, 2);
        if ($installment <= 0 || $remaining <= 0) {
            throw new InvalidArgumentException('No hay saldo para reestructurar o la cuota es inválida.');
        }

        $plan = [];
        for ($n = 0; $remaining > 0.004 && $n < 520; $n++) {
            $amount = min($installment, $remaining);
            $plan[] = ['date' => $frequency->nth($firstDate, $n)->toDateString(), 'amount' => round($amount, 2)];
            $remaining = round($remaining - $amount, 2);
        }

        return $plan;
    }

    public function restructure(Loan $loan, CarbonInterface $firstDate, PaymentFrequency $frequency, float $installment, float $extra = 0, ?string $reason = null): Loan
    {
        $this->ledger->recalculate($loan);
        $plan = $this->preview($loan, $firstDate, $frequency, $installment, $extra);

        return DB::transaction(function () use ($loan, $plan, $frequency, $extra, $reason) {
            foreach ($loan->schedules as $s) {
                if ($s->status === ScheduleStatus::Paid) {
                    continue;                                   // historial pagado: intacto
                }
                if ((float) $s->amount_paid > 0) {
                    $s->update(['amount_due' => $s->amount_paid, 'note' => 'Cerrada por reestructuración']);
                } else {
                    $s->delete();
                }
            }

            foreach ($plan as $row) {
                $loan->schedules()->create(['scheduled_date' => $row['date'], 'amount_due' => $row['amount'], 'note' => 'Reestructuración']);
            }

            $loan->forceFill([
                'total_amount' => round((float) $loan->schedules()->sum('amount_due'), 2),
                'installments_count' => $loan->schedules()->where('kind', 'installment')->count(),
                'payment_frequency' => $frequency,
                'due_date' => end($plan)['date'],
                'notes' => trim($loan->notes."\n".now()->format('d/m/Y').': reestructurado — '.count($plan).' cuota(s) de $'
                    .number_format($plan[0]['amount'], 0, ',', '.')
                    .($extra > 0 ? ' (+$'.number_format($extra, 0, ',', '.').' de interés adicional)' : '')
                    .($reason ? ". {$reason}" : '')),
            ])->save();

            return $this->ledger->recalculate($loan->fresh());
        });
    }
}
