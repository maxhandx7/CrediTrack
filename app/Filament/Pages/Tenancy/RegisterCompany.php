<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Company;
use App\Models\Plan;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;

/** Primer paso después de crear la cuenta: crear la empresa (con prueba gratis). */
class RegisterCompany extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Crea tu empresa';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nombre de tu negocio')->placeholder('Ej.: Créditos Rápidos Cali')->required()->maxLength(80)
                ->helperText('Así te verán tus clientes en los WhatsApp, recibos y su portal.'),
            TextInput::make('phone')->label('WhatsApp del negocio')->tel()->required()->maxLength(20)
                ->helperText('Aquí te llega el resumen diario de cobros.'),
        ]);
    }

    protected function handleRegistration(array $data): Company
    {
        $company = Company::create($data + [
            'status' => 'trial',
            'trial_ends_at' => today()->addDays((int) config('saas.trial_days', 14)),
            // Durante la prueba se usa el plan Profesional, para que conozcan todo.
            'plan_id' => Plan::where('slug', 'profesional')->value('id') ?? Plan::where('is_active', true)->orderBy('sort')->value('id'),
        ]);

        $company->users()->attach(auth()->user(), ['role' => 'owner']);

        return $company;
    }
}
