<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\ScheduleStatus;
use App\Models\Loan;
use App\Models\LoanSchedule;
use Illuminate\Support\Facades\DB;

/**
 * Recargo por mora: un % del saldo de la cuota, UNA vez por cuota, cuando pasa
 * de los días de gracia. Se configura por prestamista y se puede perdonar.
 */
class LateFees
{
    public function __construct(private LoanLedger $ledger) {}

    /** @return int recargos creados */
    public function apply(): int
    {
        $created = 0;

        Loan::query()
            ->where('status', LoanStatus::Late)
            ->with(['user', 'schedules.fee'])
            ->each(function (Loan $loan) use (&$created) {
                $percent = (float) $loan->user->setting('late_fee.percent', 0);
                $grace = (int) $loan->user->setting('late_fee.grace_days', 3);

                if (! $loan->user->setting('late_fee.enabled') || $percent <= 0) {
                    return;
                }

                foreach ($loan->schedules as $s) {
                    if ($s->isFee() || $s->status !== ScheduleStatus::Overdue || $s->fee || $s->daysOverdue() <= $grace) {
                        continue;
                    }

                    $amount = round($s->amount_pending * $percent / 100, 0);
                    if ($amount <= 0) {
                        continue;
                    }

                    DB::transaction(function () use ($loan, $s, $amount, $percent) {
                        $loan->schedules()->create([
                            'kind' => 'fee',
                            'parent_id' => $s->id,
                            'scheduled_date' => today(),
                            'amount_due' => $amount,
                            'note' => "Mora {$percent}% · cuota del ".$s->scheduled_date->format('d/m/Y'),
                        ]);
                        $loan->increment('total_amount', $amount);
                    });
                    $created++;
                }

                $this->ledger->recalculate($loan->fresh());
            });

        return $created;
    }

    /** Perdona un recargo: si no tiene abonos se elimina; si tiene, se deja en lo abonado. */
    public function waive(LoanSchedule $fee): void
    {
        if (! $fee->isFee()) {
            throw new \InvalidArgumentException('Solo se pueden perdonar recargos por mora.');
        }

        $fee->loadMissing('loan');

        DB::transaction(function () use ($fee) {
            $loan = $fee->loan;
            $waived = round((float) $fee->amount_due - (float) $fee->amount_paid, 2);

            (float) $fee->amount_paid > 0
                ? $fee->update(['amount_due' => $fee->amount_paid, 'note' => $fee->note.' · perdonado el resto'])
                : $fee->delete();

            $loan->forceFill(['total_amount' => max(0, round((float) $loan->total_amount - $waived, 2))])->save();
            $this->ledger->recalculate($loan->fresh());
        });
    }
}
