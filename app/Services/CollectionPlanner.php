<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\ScheduleStatus;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Enums\PromiseStatus;
use App\Models\PaymentPromise;
use App\Models\SentNotification;
use App\Notifications\PromiseReminder;
use App\Models\User;
use App\Notifications\InstallmentOverdue;
use App\Notifications\InstallmentReminder;
use App\Notifications\LenderDailyDigest;

/**
 * Decide QUÉ avisos tocan hoy. No envía nada: devuelve la lista para que el
 * comando los ponga en cola espaciados. Cada aviso se reclama en sent_notifications,
 * así que correrlo dos veces el mismo día no repite mensajes.
 *
 * @return list<array{0: object, 1: \Illuminate\Notifications\Notification}>
 */
class CollectionPlanner
{
    public function __construct(private LoanLedger $ledger, private LateFees $fees) {}

    public function plan(): array
    {
        $today = today()->toDateString();
        $messages = [];

        // 1) Poner al día los estados (cuotas que vencieron anoche, etc.)
        Loan::whereIn('status', [LoanStatus::Active, LoanStatus::Late])
            ->with(['schedules', 'payments'])
            ->each(fn (Loan $loan) => $this->ledger->recalculate($loan));

        // 1b) Recargos por mora (solo prestamistas que los activaron)
        $this->fees->apply();

        // 1c) Promesas: hoy se recuerdan; las vencidas se marcan cumplidas o incumplidas
        $broken = [];
        PaymentPromise::query()->where('status', PromiseStatus::Pending)->with('loan.client', 'loan.user')->get()
            ->each(function (PaymentPromise $p) use (&$messages, &$broken, $today) {
                if ($p->promised_date->isToday()) {
                    $client = $p->loan->client;
                    if ($client?->routeNotificationForWaha() && $p->loan->user->notifications_enabled
                        && SentNotification::claim("promise:{$p->id}:{$today}")) {
                        $messages[] = [$client, new PromiseReminder($p)];
                    }
                } elseif ($p->promised_date->isPast()) {
                    $kept = $p->paidSoFar() >= (float) $p->amount - 0.01;
                    $p->update(['status' => $kept ? PromiseStatus::Kept : PromiseStatus::Broken, 'resolved_at' => now()]);
                    if (! $kept) {
                        $broken[$p->user_id][] = $p->loan->client->name;
                    }
                }
            });

        // Préstamos con promesa vigente: no se les manda aviso de atraso (ya hay un acuerdo)
        $withPromise = PaymentPromise::where('status', PromiseStatus::Pending)
            ->whereDate('promised_date', '>=', $today)->pluck('loan_id')->all();

        $open = LoanSchedule::query()
            ->whereIn('status', [ScheduleStatus::Pending, ScheduleStatus::Overdue])
            ->whereHas('loan', fn ($q) => $q->whereIn('status', [LoanStatus::Active, LoanStatus::Late])
                ->whereHas('user', fn ($u) => $u->where('notifications_enabled', true)->where('status', '!=', 'inactive')))
            ->with('loan.client', 'loan.user')
            ->orderBy('scheduled_date')
            ->get()
            ->filter(fn (LoanSchedule $s) => $s->amount_pending > 0);

        // 2) Recordatorios: mañana y hoy
        foreach ($open as $s) {
            if ($s->isFee()) {
                continue; // los recargos se cobran junto con la cuota, sin aviso propio
            }
            $client = $s->loan->client;
            if (! $client?->routeNotificationForWaha()) {
                continue;
            }

            $when = match (true) {
                $s->scheduled_date->isTomorrow() => 'tomorrow',
                $s->scheduled_date->isToday() => 'today',
                default => null,
            };
            if ($when && SentNotification::claim("reminder:{$s->id}:{$when}")) {
                $messages[] = [$client, new InstallmentReminder($s)];
            }

            // 3) Atrasos: solo ciertos días (1, 3, 7, 15, 30), no todos los días
            if ($s->status === ScheduleStatus::Overdue
                && ! in_array($s->loan_id, $withPromise, true)
                && in_array($s->daysOverdue(), config('creditrack.overdue_reminder_days'), true)
                && SentNotification::claim("overdue:{$s->id}:{$today}")) {
                $messages[] = [$client, new InstallmentOverdue($s)];
            }
        }

        // 4) Resumen para cada prestamista con cuotas que vencen hoy o vencidas
        $open->groupBy(fn ($s) => $s->loan->user_id)->each(function ($schedules, $userId) use (&$messages, $today, $broken) {
            /** @var User $user */
            $user = $schedules->first()->loan->user;
            $dueToday = $schedules->filter(fn ($s) => $s->scheduled_date->isToday());
            $overdue = $schedules->where('status', ScheduleStatus::Overdue);

            if (($dueToday->isNotEmpty() || $overdue->isNotEmpty())
                && $user->routeNotificationForWaha()
                && SentNotification::claim("digest:{$userId}:{$today}")) {
                $messages[] = [$user, new LenderDailyDigest([
                    'due_today_count' => $dueToday->count(),
                    'due_today_total' => round((float) $dueToday->sum(fn ($s) => $s->amount_pending), 2),
                    'overdue_count' => $overdue->count(),
                    'overdue_total' => round((float) $overdue->sum(fn ($s) => $s->amount_pending), 2),
                    'overdue_clients' => $overdue->map(fn ($s) => $s->loan->client->name)->unique()->values()->all(),
                    'broken_promises' => array_values(array_unique($broken[$userId] ?? [])),
                ])];
            }
        });

        return $messages;
    }
}
