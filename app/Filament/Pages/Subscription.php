<?php

namespace App\Filament\Pages;

use App\Models\Company;
use App\Models\Plan;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Plan, estado, cómo pagar e historial. Siempre accesible, incluso con la suscripción vencida. */
class Subscription extends Page
{
    protected string $view = 'filament.pages.subscription';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Suscripción';

    public function getViewData(): array
    {
        /** @var Company $company */
        $company = Filament::getTenant()->load('plan', 'subscriptionPayments');

        return [
            'company' => $company,
            'plans' => Plan::where('is_active', true)->orderBy('sort')->get(),
            'usage' => [
                'loans' => $company->loans()->whereIn('status', ['activo', 'atrasado'])->count(),
                'users' => $company->users()->count(),
            ],
            'instructions' => config('saas.payment_instructions'),
            'support' => config('saas.support_whatsapp'),
        ];
    }
}
