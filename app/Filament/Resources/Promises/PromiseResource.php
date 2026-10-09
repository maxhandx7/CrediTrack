<?php

namespace App\Filament\Resources\Promises;

use App\Enums\PromiseStatus;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\Promises\Pages\ListPromises;
use App\Filament\Support\Money;
use App\Filament\Support\ScopedToLender;
use App\Models\PaymentPromise;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PromiseResource extends Resource
{
    use ScopedToLender;

    protected static ?string $model = PaymentPromise::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static string|UnitEnum|null $navigationGroup = 'Cobranza';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'promesa de pago';

    protected static ?string $pluralModelLabel = 'promesas de pago';

    public static function canCreate(): bool
    {
        return false; // se registran desde el préstamo o la bandeja de cobro
    }

    public static function getNavigationBadge(): ?string
    {
        $today = static::getEloquentQuery()->where('status', PromiseStatus::Pending)->whereDate('promised_date', today())->count();

        return $today ? (string) $today : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('loan.client'))
            ->defaultSort('promised_date')
            ->columns([
                TextColumn::make('loan.client.name')->label('Cliente')->searchable()
                    ->url(fn (PaymentPromise $record) => LoanResource::getUrl('view', ['record' => $record->loan_id])),
                TextColumn::make('promised_date')->label('Paga el')->date('d M Y')->sortable()
                    ->description(fn (PaymentPromise $record) => $record->promised_date->isToday() ? 'Hoy' : $record->promised_date->diffForHumans()),
                TextColumn::make('amount')->label('Valor')->alignEnd()->formatStateUsing(fn ($state) => Money::cop($state)),
                TextColumn::make('notes')->label('Notas')->limit(60)->placeholder('—')->toggleable(),
                TextColumn::make('status')->label('Estado')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(PromiseStatus::class)->default(PromiseStatus::Pending->value),
            ])
            ->recordActions([
                DeleteAction::make()->iconButton()->visible(fn (PaymentPromise $record) => $record->status === PromiseStatus::Pending),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPromises::route('/')];
    }
}
