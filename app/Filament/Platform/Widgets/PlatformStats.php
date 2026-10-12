<?php

namespace App\Filament\Platform\Widgets;

use App\Filament\Support\Money;
use App\Models\Company;
use App\Models\SubscriptionPayment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $companies = Company::with('plan')->get();
        $active = $companies->filter(fn ($c) => $c->status === 'active' && $c->hasAccess());
        $mrr = $active->filter(fn ($c) => $c->paid_until !== null)->sum(fn ($c) => (float) $c->plan?->price_monthly);
        $month = (float) SubscriptionPayment::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount');
        $expiring = $companies->filter(fn ($c) => $c->hasAccess() && ($d = $c->daysLeft()) !== null && $d <= 7)->count();

        return [
            Stat::make('Empresas activas', $active->count())->description($companies->where('status', 'trial')->count().' en prueba')->color('success'),
            Stat::make('Ingreso mensual recurrente', Money::cop($mrr))->description('Suma de planes pagos y vigentes'),
            Stat::make('Cobrado este mes', Money::cop($month))->color('primary'),
            Stat::make('Por vencer (7 días)', $expiring)->description($companies->whereIn('status', ['past_due', 'suspended'])->count().' vencidas o suspendidas')
                ->color($expiring > 0 ? 'warning' : 'gray'),
        ];
    }
}
