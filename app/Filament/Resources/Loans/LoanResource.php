<?php

namespace App\Filament\Resources\Loans;

use App\Enums\InterestType;
use App\Enums\LoanStatus;
use App\Enums\PaymentFrequency;
use App\Filament\Resources\Loans\Pages\CreateLoan;
use App\Filament\Resources\Loans\Pages\EditLoan;
use App\Filament\Resources\Loans\Pages\ListLoans;
use App\Filament\Resources\Loans\Pages\ViewLoan;
use App\Filament\Resources\Loans\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Loans\RelationManagers\PromisesRelationManager;
use App\Filament\Resources\Loans\RelationManagers\SchedulesRelationManager;
use App\Filament\Support\LoanActions;
use App\Filament\Support\Money;
use App\Filament\Support\ScopedToLender;
use App\Models\Client;
use App\Models\Loan;
use App\Services\ScheduleGenerator;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnitEnum;

class LoanResource extends Resource
{
    use ScopedToLender;

    protected static ?string $model = Loan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Cartera';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'préstamo';

    public static function getNavigationBadge(): ?string
    {
        $late = static::getEloquentQuery()->where('status', LoanStatus::Late)->count();

        return $late ? (string) $late : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Préstamos en mora';
    }

    public static function form(Schema $schema): Schema
    {
        $locked = fn (?Loan $record) => (bool) $record?->validPayments()->exists();

        return $schema->columns(3)->components([
            Section::make('Condiciones')->columnSpan(2)->columns(2)
                ->description(fn (?Loan $record) => $locked($record) ? 'Este préstamo ya tiene pagos: para cambiar condiciones usa "Reestructurar".' : null)
                ->schema([
                    Select::make('client_id')->label('Cliente')->required()->searchable()->preload()->columnSpanFull()
                        ->relationship('client', 'name', fn (Builder $query) => $query->where('user_id', auth()->id()))
                        ->getOptionLabelFromRecordUsing(fn (Client $record) => "{$record->name} · C.C. {$record->document}")
                        ->disabled(fn (?Loan $record) => $record !== null),
                    TextInput::make('amount')->label('Monto prestado')->numeric()->prefix('$')->required()->minValue(1)->live(onBlur: true)->disabled($locked),
                    TextInput::make('interest_rate')->label('Interés por periodo')->numeric()->suffix('%')->required()->minValue(0)->maxValue(100)->default(10)->live(onBlur: true)->disabled($locked),
                    Select::make('interest_type')->label('Tipo de interés')->options(InterestType::class)->default(InterestType::Simple)->required()->live()->disabled($locked),
                    Select::make('payment_frequency')->label('Frecuencia de pago')->options(PaymentFrequency::class)->default(PaymentFrequency::Biweekly)->required()->live()->disabled($locked),
                    DatePicker::make('start_date')->label('Fecha del desembolso')->default(today())->native(false)->required()->live()->disabled($locked),
                    DatePicker::make('due_date')->label('Fecha final')->default(today()->addMonths(3))->native(false)->required()->live()->afterOrEqual('start_date')->disabled($locked),
                    Text::make(fn (Get $get, ?Loan $record) => $record && $locked($record) ? '' : self::simulation($get))->columnSpanFull(),
                ]),
            Grid::make(1)->columnSpan(1)->schema([
                Section::make('Notas')->schema([Textarea::make('notes')->hiddenLabel()->rows(4)]),
                Section::make('Documentos')->collapsible()->schema([
                    FileUpload::make('documents')->hiddenLabel()->multiple()->disk('local')->directory('prestamos')
                        ->visibility('private')->acceptedFileTypes(['image/*', 'application/pdf'])->maxSize(5120)->openable()->downloadable()
                        ->helperText('Pagaré, letra de cambio, soportes…'),
                ]),
            ]),
        ]);
    }

    /** Vista previa en vivo: cuántas cuotas, de cuánto y cuánto gana el prestamista. */
    public static function simulation(Get $get): string
    {
        try {
            $value = fn ($v) => $v instanceof BackedEnum ? $v : null;
            $type = $value($get('interest_type')) ?? InterestType::from($get('interest_type'));
            $freq = $value($get('payment_frequency')) ?? PaymentFrequency::from($get('payment_frequency'));
            $plan = app(ScheduleGenerator::class)->plan((float) $get('amount'), (float) $get('interest_rate'), $type, $freq, $get('start_date'), $get('due_date'));
            $n = count($plan['installments']);

            return "→ {$n} cuota(s) ".mb_strtolower($freq->getLabel())."s de ".Money::cop($plan['installments'][0]['amount'])
                .' · total a pagar '.Money::cop($plan['total']).' · ganancia '.Money::cop($plan['total'] - (float) $get('amount')).'.';
        } catch (Throwable) {
            return 'Completa monto, tasa y fechas para ver el plan de cuotas.';
        }
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(4)->components([
            Section::make()->columnSpanFull()->columns(4)->schema([
                TextEntry::make('client.name')->label('Cliente')->weight('bold')
                    ->url(fn (Loan $record) => \App\Filament\Resources\Clients\ClientResource::getUrl('edit', ['record' => $record->client_id])),
                TextEntry::make('status')->label('Estado')->badge(),
                TextEntry::make('balance')->label('Saldo pendiente')->state(fn (Loan $record) => Money::cop($record->balance))->size('lg')->weight('bold'),
                TextEntry::make('overdue')->label('En mora')->state(fn (Loan $record) => $record->overdueAmount() > 0 ? Money::cop($record->overdueAmount()) : 'Al día')
                    ->color(fn (Loan $record) => $record->overdueAmount() > 0 ? 'danger' : 'success'),
                TextEntry::make('amount')->label('Capital')->formatStateUsing(fn ($state) => Money::cop($state)),
                TextEntry::make('total_amount')->label('Total a pagar')->formatStateUsing(fn ($state) => Money::cop($state)),
                TextEntry::make('total_paid')->label('Pagado')->state(fn (Loan $record) => Money::cop($record->total_paid).' ('.$record->progress().'%)'),
                TextEntry::make('next')->label('Próxima cuota')
                    ->state(fn (Loan $record) => ($n = $record->nextSchedule()) ? Money::cop($n->amount_pending).' · '.$n->scheduled_date->translatedFormat('j M Y') : '—'),
                TextEntry::make('terms')->label('Condiciones')->columnSpan(2)
                    ->state(fn (Loan $record) => rtrim(rtrim(number_format($record->interest_rate, 2, ',', '.'), '0'), ',').'% '
                        .mb_strtolower($record->interest_type->getLabel()).' · '.mb_strtolower($record->payment_frequency->getLabel())
                        .' · '.$record->start_date->format('d/m/Y').' → '.$record->due_date->format('d/m/Y')),
                TextEntry::make('notes')->label('Notas')->columnSpan(2)->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['client', 'schedules', 'payments']))
            ->defaultSort('start_date', 'desc')
            ->recordUrl(fn (Loan $record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('client.name')->label('Cliente')->searchable()->weight('bold')
                    ->description(fn (Loan $record) => '#'.$record->id.' · '.mb_strtolower($record->payment_frequency->getLabel())),
                TextColumn::make('amount')->label('Capital')->sortable()->formatStateUsing(fn ($state) => Money::cop($state)),
                TextColumn::make('balance')->label('Saldo')->state(fn (Loan $record) => $record->balance)->weight('bold')
                    ->formatStateUsing(fn ($state) => Money::cop($state))
                    ->description(fn (Loan $record) => $record->progress().'% pagado'),
                TextColumn::make('next')->label('Próxima cuota')
                    ->state(fn (Loan $record) => $record->nextSchedule()?->scheduled_date)
                    ->date('d M Y')->placeholder('—')
                    ->description(fn (Loan $record) => ($n = $record->nextSchedule()) ? Money::cop($n->amount_pending) : null)
                    ->color(fn (Loan $record) => $record->nextSchedule()?->status?->value === 'vencido' ? 'danger' : null),
                TextColumn::make('status')->label('Estado')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(LoanStatus::class)->multiple()
                    ->default([LoanStatus::Active->value, LoanStatus::Late->value]),
                SelectFilter::make('client')->label('Cliente')->searchable()->preload()
                    ->relationship('client', 'name', fn (Builder $query) => $query->where('user_id', auth()->id())),
            ])
            ->recordActions([
                LoanActions::registerPayment()->iconButton(),
                ViewAction::make()->iconButton(),
                ActionGroup::make([
                    LoanActions::promise(),
                    LoanActions::restructure(),
                    LoanActions::statement(),
                    LoanActions::sendStatement(),
                ]),
            ])
            ->emptyStateHeading('Sin préstamos')
            ->emptyStateDescription('Crea el primero o prueba el simulador.');
    }

    public static function canEdit(Model $record): bool
    {
        return $record->status !== LoanStatus::Paid;
    }

    public static function getRelations(): array
    {
        return [SchedulesRelationManager::class, PaymentsRelationManager::class, PromisesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoans::route('/'),
            'create' => CreateLoan::route('/create'),
            'view' => ViewLoan::route('/{record}'),
            'edit' => EditLoan::route('/{record}/edit'),
        ];
    }
}
