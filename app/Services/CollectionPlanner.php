<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\ScheduleStatus;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Models\SentNotification;
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
    public function __construct(private LoanLedger $ledger) {}

    public function plan(): array
    {
        $today = today()->toDateString();
        $messages = [];

        // 1) Poner al día los estados (cuotas que vencieron anoche, etc.)
        Loan::whereIn('status', [LoanStatus::Active, LoanStatus::Late])
            ->with(['schedules', 'payments'])
            ->each(fn (Loan $loan) => $this->ledger->recalculate($loan));

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
                && in_array($s->daysOverdue(), config('creditrack.overdue_reminder_days'), true)
                && SentNotification::claim("overdue:{$s->id}:{$today}")) {
                $messages[] = [$client, new InstallmentOverdue($s)];
            }
        }

        // 4) Resumen para cada prestamista con cuotas que vencen hoy o vencidas
        $open->groupBy(fn ($s) => $s->loan->user_id)->each(function ($schedules, $userId) use (&$messages, $today) {
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
                ])];
            }
        });

        return $messages;
    }
}
