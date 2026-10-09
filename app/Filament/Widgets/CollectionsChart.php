<?php

namespace App\Filament\Widgets;

use App\Models\LoanSchedule;
use App\Models\Payment;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class CollectionsChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Recaudo vs. esperado — últimos 6 meses';

    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 2];

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $uid = auth()->id();
        $months = collect(range(5, 0))->map(fn ($ago) => today()->subMonthsNoOverflow($ago)->startOfMonth());

        $collected = $months->map(fn ($m) => (float) Payment::where('user_id', $uid)->whereNull('voided_at')
            ->whereBetween('date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])->sum('amount'))->all();
        $expected = $months->map(fn ($m) => (float) LoanSchedule::whereHas('loan', fn ($q) => $q->where('user_id', $uid)->where('status', '!=', 'cancelado'))
            ->whereBetween('scheduled_date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])->sum('amount_due'))->all();

        return [
            'datasets' => [
                ['label' => 'Esperado', 'data' => $expected, 'backgroundColor' => '#cbd5e1', 'borderRadius' => 4],
                ['label' => 'Recaudado', 'data' => $collected, 'backgroundColor' => '#0f766e', 'borderRadius' => 4],
            ],
            'labels' => $months->map(fn ($m) => ucfirst($m->translatedFormat('M y')))->all(),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                scales: { y: { ticks: { callback: (v) => '$' + Intl.NumberFormat('es-CO', { notation: 'compact' }).format(v) } } },
                plugins: { tooltip: { callbacks: { label: (c) => c.dataset.label + ': $' + Intl.NumberFormat('es-CO').format(c.parsed.y) } } },
            }
        JS);
    }
}
