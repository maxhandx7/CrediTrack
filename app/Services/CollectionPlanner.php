<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\PromiseStatus;
use App\Enums\ScheduleStatus;
use App\Models\Company;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Models\MessageLog;
use App\Models\PaymentPromise;
use App\Models\SentNotification;
use App\Notifications\InstallmentOverdue;
use App\Notifications\InstallmentReminder;
use App\Notifications\LenderDailyDigest;
use App\Notifications\PromiseReminder;

/**
 * Decide QUÉ avisos tocan hoy, empresa por empresa, según SUS opciones:
 * qué avisos envía, en qué días de atraso, a qué hora, si descansa los domingos y
 * cuántos mensajes puede mandar al día (protege el número compartido).
 * No envía nada: devuelve la lista. Cada aviso se "reclama" una sola vez.
 *
 * @return list<array{0: object, 1: \Illuminate\Notifications\Notification}>
 */
class CollectionPlanner
{
    public function __construct(private LoanLedger $ledger, private LateFees $fees) {}

    /** @param int|null $hour solo empresas que envían a esta hora (null = todas) */
    public function plan(?int $hour = null): array
    {
        $messages = [];

        Company::query()->with('plan')->get()
            ->filter(fn (Company $c) => $c->hasAccess())
            ->each(function (Company $company) use (&$messages, $hour) {
                $this->ledgerFor($company);
                if ($hour !== null && (int) $company->setting('notify.send_hour') !== $hour) {
                    return;
                }
                if (! $company->setting('notify.enabled') || ($company->setting('notify.skip_sundays') && today()->isSunday())) {
                    return;
                }
                array_push($messages, ...$this->planFor($company));
            });

        return $messages;
    }

    /** Estados al día y recargos de mora: corre siempre, aunque la empresa no envíe avisos. */
    private function ledgerFor(Company $company): void
    {
        $company->loans()->whereIn('status', [LoanStatus::Active, LoanStatus::Late])
            ->with(['schedules', 'payments'])
            ->each(fn (Loan $loan) => $this->ledger->recalculate($loan));
        $this->fees->apply($company);
    }

    private function planFor(Company $company): array
    {
        $today = today()->toDateString();
        $budget = max(0, $company->dailyMessageLimit() - MessageLog::where('company_id', $company->id)->whereDate('created_at', $today)->count());
        $messages = [];
        $add = function (object $to, $notification, string $key) use (&$messages, &$budget) {
            if ($budget > 0 && SentNotification::claim($key)) {
                $messages[] = [$to, $notification];
                $budget--;
            }
        };

        // Promesas: hoy se recuerdan; las vencidas se cierran como cumplidas o incumplidas
        $broken = [];
        $company->paymentPromises()->where('status', PromiseStatus::Pending)->with('loan.client')->get()
            ->each(function (PaymentPromise $p) use ($company, $add, &$broken, $today) {
                if ($p->promised_date->isToday()) {
                    if ($company->setting('notify.promise') && $p->loan->client?->routeNotificationForWaha()) {
                        $add($p->loan->client, new PromiseReminder($p), "promise:{$p->id}:{$today}");
                    }
                } elseif ($p->promised_date->isPast()) {
                    $kept = $p->paidSoFar() >= (float) $p->amount - 0.01;
                    $p->update(['status' => $kept ? PromiseStatus::Kept : PromiseStatus::Broken, 'resolved_at' => now()]);
                    if (! $kept) {
                        $broken[] = $p->loan->client->name;
                    }
                }
            });

        $withPromise = $company->paymentPromises()->where('status', PromiseStatus::Pending)
            ->whereDate('promised_date', '>=', $today)->pluck('loan_id')->all();
        $overdueDays = array_map('intval', array_filter(explode(',', (string) $company->setting('notify.overdue_days'))));

        $open = LoanSchedule::query()
            ->where('kind', 'installment')
            ->whereIn('status', [ScheduleStatus::Pending, ScheduleStatus::Overdue])
            ->whereHas('loan', fn ($q) => $q->where('company_id', $company->id)->whereIn('status', [LoanStatus::Active, LoanStatus::Late]))
            ->with('loan.client')
            ->orderBy('scheduled_date')
            ->get()
            ->filter(fn (LoanSchedule $s) => $s->amount_pending > 0);

        foreach ($open as $s) {
            $client = $s->loan->client;
            if (! $client?->routeNotificationForWaha()) {
                continue;
            }

            if ($s->scheduled_date->isTomorrow() && $company->setting('notify.reminder_before')) {
                $add($client, new InstallmentReminder($s), "reminder:{$s->id}:tomorrow");
            }
            if ($s->scheduled_date->isToday() && $company->setting('notify.reminder_today')) {
                $add($client, new InstallmentReminder($s), "reminder:{$s->id}:today");
            }
            if ($s->status === ScheduleStatus::Overdue && $company->setting('notify.overdue')
                && ! in_array($s->loan_id, $withPromise, true)
                && in_array($s->daysOverdue(), $overdueDays, true)) {
                $add($client, new InstallmentOverdue($s), "overdue:{$s->id}:{$today}");
            }
        }

        // Resumen para la empresa (a su WhatsApp), si hay algo que reportar
        $dueToday = $open->filter(fn ($s) => $s->scheduled_date->isToday());
        $overdue = $open->where('status', ScheduleStatus::Overdue);
        if (($dueToday->isNotEmpty() || $overdue->isNotEmpty() || $broken !== []) && $company->routeNotificationForWaha()
            && SentNotification::claim("digest:company:{$company->id}:{$today}")) {
            $messages[] = [$company, new LenderDailyDigest([
                'due_today_count' => $dueToday->count(),
                'due_today_total' => round((float) $dueToday->sum(fn ($s) => $s->amount_pending), 2),
                'overdue_count' => $overdue->count(),
                'overdue_total' => round((float) $overdue->sum(fn ($s) => $s->amount_pending), 2),
                'overdue_clients' => $overdue->map(fn ($s) => $s->loan->client->name)->unique()->values()->all(),
                'broken_promises' => array_values(array_unique($broken)),
            ])];
        }

        return $messages;
    }
}
