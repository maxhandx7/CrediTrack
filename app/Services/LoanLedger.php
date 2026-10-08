<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\ScheduleStatus;
use App\Models\Loan;
use Illuminate\Support\Facades\DB;

/**
 * El libro contable de un préstamo. Es la ÚNICA fuente de verdad de saldos y estados:
 * reparte los pagos (del más viejo al más nuevo) sobre las cuotas (de la más vieja
 * a la más nueva) y a partir de ahí decide qué cuota está pagada, pendiente o
 * vencida, y si el préstamo está activo, atrasado o pagado.
 *
 * Se recalcula todo desde cero cada vez: así crear, editar o borrar un pago
 * nunca deja el préstamo en un estado inconsistente.
 */
class LoanLedger
{
    public function recalculate(Loan $loan): Loan
    {
        return DB::transaction(function () use ($loan) {
            $loan->load(['schedules', 'payments' => fn ($q) => $q->orderBy('date')->orderBy('id')]);

            $schedules = $loan->schedules->values();
            $total = (float) $loan->total_amount;
            $paidSoFar = 0.0;
            $cursor = 0;
            $credit = [];   // índice de cuota => abonado
            $paidOn = [];   // índice de cuota => fecha en que quedó cubierta

            foreach ($loan->payments as $payment) {
                $available = (float) $payment->amount;
                $paidSoFar += $available;

                while ($available > 0.004 && $cursor < $schedules->count()) {
                    $due = (float) $schedules[$cursor]->amount_due;
                    $current = $credit[$cursor] ?? 0.0;
                    $apply = min($available, round($due - $current, 2));
                    $credit[$cursor] = round($current + $apply, 2);
                    $available = round($available - $apply, 2);

                    if ($credit[$cursor] >= $due - 0.004) {
                        $paidOn[$cursor] = $payment->date->toDateString();
                        $cursor++;
                    }
                }

                $remaining = max(0, round($total - $paidSoFar, 2));
                if ((float) $payment->remaining_balance !== $remaining) {
                    $payment->forceFill(['remaining_balance' => $remaining])->saveQuietly();
                }
            }

            $hasOverdue = false;
            foreach ($schedules as $i => $schedule) {
                $paid = $credit[$i] ?? 0.0;
                $status = isset($paidOn[$i])
                    ? ScheduleStatus::Paid
                    : ($schedule->scheduled_date->lt(today()) ? ScheduleStatus::Overdue : ScheduleStatus::Pending);
                $hasOverdue = $hasOverdue || $status === ScheduleStatus::Overdue;

                $schedule->forceFill([
                    'amount_paid' => $paid,
                    'paid_at' => $paidOn[$i] ?? null,
                    'status' => $status,
                ]);
                if ($schedule->isDirty()) {
                    $schedule->saveQuietly();
                }
            }

            if ($loan->status !== LoanStatus::Cancelled) {
                $loan->status = match (true) {
                    $total > 0 && $paidSoFar >= $total - 0.004 => LoanStatus::Paid,
                    $hasOverdue => LoanStatus::Late,
                    default => LoanStatus::Active,
                };
                if ($loan->isDirty('status')) {
                    $loan->saveQuietly();
                }
            }

            return $loan;
        });
    }
}
