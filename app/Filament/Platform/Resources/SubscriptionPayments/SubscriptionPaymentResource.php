<?php

namespace App\Filament\Platform\Resources\SubscriptionPayments;

use App\Filament\Platform\Resources\SubscriptionPayments\Pages\ListSubscriptionPayments;
use App\Filament\Support\Money;
use App\Models\SubscriptionPayment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SubscriptionPaymentResource extends Resource
{
    protected static ?string $model = SubscriptionPayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Negocio';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'pago de suscripción';

    protected static ?string $pluralModelLabel = 'pagos de suscripción';

    public static function canCreate(): bool
    {
        return false; // se registran desde la empresa ("Registrar pago")
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with(['company', 'plan']))->columns([
            TextColumn::make('created_at')->label('Fecha')->date('d M Y')->sortable(),
            TextColumn::make('company.name')->label('Empresa')->searchable(),
            TextColumn::make('plan.name')->label('Plan')->badge()->color('gray'),
            TextColumn::make('months')->label('Meses')->alignCenter(),
            TextColumn::make('period_end')->label('Cubre hasta')->date('d M Y'),
            TextColumn::make('method')->label('Medio')->badge()->color('gray'),
            TextColumn::make('reference')->label('Referencia')->placeholder('—'),
            TextColumn::make('amount')->label('Valor')->alignEnd()->weight('bold')->formatStateUsing(fn ($state) => Money::cop($state)),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSubscriptionPayments::route('/')];
    }
}
