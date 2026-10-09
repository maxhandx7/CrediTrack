<?php

namespace App\Filament\Widgets;

use App\Enums\ScheduleStatus;
use App\Filament\Support\Money;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Models\Payment;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PortfolioOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|array|null $columns = ['md' => 2, 'xl' => 4];

    protected function getStats(): array
    {
        $uid = auth()->id();
        $open = Loan::where('user_id', $uid)->whereIn('status', ['activo', 'atrasado'])->with(['payments', 'schedules'])->get();

        $receivable = $open->sum(fn (Loan $l) => $l->balance);
        $capitalOut = $open->sum(fn (Loan $l) => max(0, (float) $l->amount - $l->total_paid));
        $overdue = $open->sum(fn (Loan $l) => $l->overdueAmount());
        $overduePct = $receivable > 0 ? round($overdue / $receivable * 100, 1) : 0;

        $all = Loan::where('user_id', $uid)->whereIn('status', ['activo', 'atrasado', 'pagado'])->with('payments')->get();
        $interestEarned = $all->sum(fn (Loan $l) => max(0, $l->total_paid - (float) $l->amount));

        [$from, $to] = [today()->startOfMonth(), today()->endOfMonth()];
        $collected = (float) Payment::where('user_id', $uid)->whereNull('voided_at')->whereBetween('date', [$from->toDateString(), $to->toDateString()])->sum('amount');
        $expected = (float) LoanSchedule::whereHas('loan', fn ($q) => $q->where('user_id', $uid)->whereIn('status', ['activo', 'atrasado', 'pagado']))
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()])->sum('amount_due');

        return [
            Stat::make('Por cobrar', Money::cop($receivable))
                ->description($open->count().' préstamo(s) activo(s) · capital en la calle '.Money::cop($capitalOut))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)->color('primary'),
            Stat::make('En mora', Money::cop($overdue))
                ->description($overduePct.'% de la cartera · '.$open->where('status.value', 'atrasado')->count().' préstamo(s)')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($overduePct > 15 ? 'danger' : ($overduePct > 5 ? 'warning' : 'success')),
            Stat::make('Recaudado este mes', Money::cop($collected))
                ->description($expected > 0 ? 'de '.Money::cop($expected).' esperados ('.round($collected / $expected * 100).'%)' : 'Sin cuotas este mes')
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
                ->color($expected > 0 && $collected >= $expected ? 'success' : 'info')
                ->chart($this->lastMonths()),
            Stat::make('Intereses cobrados', Money::cop($interestEarned))
                ->description('Ganancia ya recibida, sin contar el capital')
                ->descriptionIcon(Heroicon::OutlinedSparkles)->color('success'),
        ];
    }

    private function lastMonths(): array
    {
        return collect(range(5, 0))->map(function ($ago) {
            $m = today()->subMonthsNoOverflow($ago);

            return (float) Payment::where('user_id', auth()->id())->whereNull('voided_at')
                ->whereBetween('date', [$m->copy()->startOfMonth()->toDateString(), $m->copy()->endOfMonth()->toDateString()])->sum('amount');
        })->all();
    }
}
