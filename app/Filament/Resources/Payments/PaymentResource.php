<?php

namespace App\Filament\Resources\Payments;

use App\Enums\PaymentMethod;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Support\Money;
use App\Filament\Support\ScopedToLender;
use App\Models\Payment;
use App\Services\Payments;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class PaymentResource extends Resource
{
    use ScopedToLender;

    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Cobranza';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'pago';

    public static function canCreate(): bool
    {
        return false; // los pagos se registran desde el préstamo o la bandeja de cobro
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('loan.client'))
            ->defaultSort('date', 'desc')
            ->recordClasses(fn (Payment $record) => $record->isVoided() ? 'opacity-50' : null)
            ->columns([
                TextColumn::make('receipt_number')->label('Recibo')->formatStateUsing(fn (Payment $record) => $record->receiptLabel())->sortable()
                    ->description(fn (Payment $record) => $record->isVoided() ? 'Anulado: '.$record->void_reason : null),
                TextColumn::make('loan.client.name')->label('Cliente')->searchable()
                    ->url(fn (Payment $record) => LoanResource::getUrl('view', ['record' => $record->loan_id])),
                TextColumn::make('date')->label('Fecha')->date('d M Y')->sortable(),
                TextColumn::make('method')->label('Medio')->badge()->color('gray')
                    ->description(fn (Payment $record) => $record->reference),
                TextColumn::make('amount')->label('Valor')->alignEnd()->sortable()->weight('bold')
                    ->formatStateUsing(fn ($state) => Money::cop($state)),
                TextColumn::make('remaining_balance')->label('Saldo después')->alignEnd()
                    ->formatStateUsing(fn ($state) => Money::cop($state))->toggleable(),
            ])
            ->filters([
                SelectFilter::make('method')->label('Medio')->options(PaymentMethod::class),
                TernaryFilter::make('voided_at')->label('Anulados')->nullable()->trueLabel('Solo anulados')->falseLabel('Solo válidos')->default(false),
                Filter::make('period')->schema([
                    DatePicker::make('from')->label('Desde')->native(false),
                    DatePicker::make('until')->label('Hasta')->native(false),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('date', '>=', $d))
                    ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('date', '<=', $d))),
            ])
            ->recordActions([
                Action::make('receipt')->label('Recibo')->icon(Heroicon::OutlinedDocumentText)->iconButton()->color('gray')
                    ->url(fn (Payment $record) => $record->receiptUrl(), true),
                Action::make('attachment')->label('Comprobante')->icon(Heroicon::OutlinedPaperClip)->iconButton()->color('gray')
                    ->visible(fn (Payment $record) => filled($record->attachment))
                    ->url(fn (Payment $record) => Storage::disk('local')->temporaryUrl($record->attachment, now()->addMinutes(10)), true),
                Action::make('void')->label('Anular')->icon(Heroicon::OutlinedXCircle)->iconButton()->color('danger')
                    ->visible(fn (Payment $record) => ! $record->isVoided())
                    ->modalHeading(fn (Payment $record) => 'Anular '.$record->receiptLabel())
                    ->modalDescription('El pago queda en el historial como anulado y deja de contar en el saldo.')
                    ->schema([TextInput::make('reason')->label('Motivo')->required()->placeholder('Ej.: se registró dos veces')])
                    ->action(function (Payment $record, array $data) {
                        app(Payments::class)->void($record, $data['reason']);
                        Notification::make()->success()->title($record->receiptLabel().' anulado')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPayments::route('/')];
    }
}
