<?php

namespace App\Filament\Support;

use App\Enums\LoanStatus;
use App\Enums\PaymentFrequency;
use App\Enums\PaymentMethod;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Models\PaymentPromise;
use App\Services\LoanLedger;
use App\Services\LoanRestructurer;
use App\Services\Payments;
use App\Services\Waha\WahaClient;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Acciones sobre un préstamo, reutilizables desde el préstamo, la bandeja de cobro
 * (donde el registro es una cuota) y el dashboard.
 */
class LoanActions
{
    public static function loan(Model $record): Loan
    {
        return $record instanceof Loan ? $record : $record->loan;
    }

    private static function open(Model $record): bool
    {
        return in_array(self::loan($record)->status, [LoanStatus::Active, LoanStatus::Late], true);
    }

    public static function registerPayment(): Action
    {
        return Action::make('registerPayment')
            ->label('Registrar pago')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (Model $record) => self::open($record))
            ->modalHeading(fn (Model $record) => 'Pago de '.self::loan($record)->client->name)
            ->modalDescription(fn (Model $record) => 'Saldo actual: '.Money::cop(self::loan($record)->balance)
                .(($next = self::loan($record)->nextSchedule()) ? ' · cuota pendiente: '.Money::cop($next->amount_pending) : ''))
            ->schema(fn (Model $record) => [
                Grid::make(2)->schema([
                    TextInput::make('amount')->label('Valor')->numeric()->prefix('$')->required()->minValue(1)
                        ->maxValue(self::loan($record)->balance + 0.01)
                        ->default(self::loan($record)->nextSchedule()?->amount_pending),
                    DatePicker::make('date')->label('Fecha')->default(today())->maxDate(today())->native(false)->required(),
                    Select::make('method')->label('Medio de pago')->options(PaymentMethod::class)->default(PaymentMethod::Cash)->required(),
                    TextInput::make('reference')->label('Referencia')->placeholder('N.º de transacción'),
                ]),
                FileUpload::make('attachment')->label('Comprobante (foto o PDF)')->disk('local')->directory('comprobantes')
                    ->visibility('private')->acceptedFileTypes(['image/*', 'application/pdf'])->maxSize(4096),
                Textarea::make('notes')->label('Notas')->rows(2),
                Toggle::make('notify')->label('Enviar recibo por WhatsApp al cliente')
                    ->default(filled(self::loan($record)->client->routeNotificationForWaha()) && app(WahaClient::class)->enabled())
                    ->disabled(blank(self::loan($record)->client->routeNotificationForWaha()) || ! app(WahaClient::class)->enabled()),
            ])
            ->action(function (Model $record, array $data) {
                try {
                    $payment = app(Payments::class)->register(self::loan($record), $data, (bool) ($data['notify'] ?? false));
                    Notification::make()->success()->title('Pago registrado: '.$payment->receiptLabel())
                        ->body('Saldo pendiente: '.Money::cop($payment->remaining_balance))->send();
                } catch (Throwable $e) {
                    Notification::make()->danger()->title('No se registró el pago')->body($e->getMessage())->send();
                }
            });
    }

    public static function promise(): Action
    {
        return Action::make('promise')
            ->label('Promesa de pago')
            ->icon(Heroicon::OutlinedHandRaised)
            ->color('warning')
            ->visible(fn (Model $record) => self::open($record))
            ->modalHeading('Registrar promesa de pago')
            ->modalDescription('Ese día se le recuerda al cliente, y mientras la promesa esté vigente no se le envían avisos de atraso.')
            ->schema(fn (Model $record) => [
                Grid::make(2)->schema([
                    DatePicker::make('promised_date')->label('Paga el')->minDate(today())->default(today()->addDays(3))->native(false)->required(),
                    TextInput::make('amount')->label('Valor prometido')->numeric()->prefix('$')->required()->minValue(1)
                        ->default(self::loan($record)->overdueAmount() ?: self::loan($record)->nextSchedule()?->amount_pending),
                ]),
                Textarea::make('notes')->label('Notas')->placeholder('Ej.: le pagan la quincena el viernes')->rows(2),
            ])
            ->action(function (Model $record, array $data) {
                $loan = self::loan($record);
                PaymentPromise::create($data + ['user_id' => $loan->user_id, 'loan_id' => $loan->id]);
                Notification::make()->success()->title('Promesa registrada para el '.Carbon::parse($data['promised_date'])->translatedFormat('j \d\e F'))->send();
            });
    }

    public static function restructure(): Action
    {
        return Action::make('restructure')
            ->label('Reestructurar')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (Model $record) => self::open($record) && self::loan($record)->balance > 0)
            ->modalHeading('Reestructurar lo que falta por pagar')
            ->modalDescription(fn (Model $record) => 'Saldo a reorganizar: '.Money::cop(self::loan($record)->balance).'. Las cuotas ya pagadas se conservan.')
            ->schema(fn (Model $record) => [
                Grid::make(2)->schema([
                    TextInput::make('installment')->label('Valor de cada cuota')->numeric()->prefix('$')->required()->minValue(1)->live(onBlur: true)
                        ->default(self::loan($record)->nextSchedule()?->amount_due),
                    Select::make('frequency')->label('Frecuencia')->options(PaymentFrequency::class)->required()->live()
                        ->default(self::loan($record)->payment_frequency),
                    DatePicker::make('first_date')->label('Primera cuota')->native(false)->required()->live()->default(today()->addWeek()),
                    TextInput::make('extra')->label('Interés adicional (opcional)')->numeric()->prefix('$')->default(0)->live(onBlur: true),
                ]),
                Text::make(function (Get $get) use ($record) {
                    try {
                        $plan = app(LoanRestructurer::class)->preview(self::loan($record), Carbon::parse($get('first_date')),
                            PaymentFrequency::from($get('frequency') instanceof PaymentFrequency ? $get('frequency')->value : $get('frequency')),
                            (float) $get('installment'), (float) $get('extra'));

                        return '→ '.count($plan).' cuota(s): la primera el '.Carbon::parse($plan[0]['date'])->translatedFormat('j M Y')
                            .', la última el '.Carbon::parse(end($plan)['date'])->translatedFormat('j M Y')
                            .' por '.Money::cop(end($plan)['amount']).'.';
                    } catch (Throwable) {
                        return 'Completa los datos para ver el nuevo plan.';
                    }
                }),
                TextInput::make('reason')->label('Motivo')->placeholder('Ej.: acordado con el cliente por cambio de trabajo'),
            ])
            ->action(function (Model $record, array $data) {
                $loan = app(LoanRestructurer::class)->restructure(
                    self::loan($record), Carbon::parse($data['first_date']), PaymentFrequency::from($data['frequency'] instanceof PaymentFrequency ? $data['frequency']->value : $data['frequency']),
                    (float) $data['installment'], (float) ($data['extra'] ?? 0), $data['reason'] ?? null,
                );
                Notification::make()->success()->title('Préstamo reestructurado')->body('Estado: '.$loan->status->getLabel().' · saldo '.Money::cop($loan->balance))->send();
            });
    }

    public static function statement(): Action
    {
        return Action::make('statement')
            ->label('Estado de cuenta')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->url(fn (Model $record) => self::loan($record)->statementUrl())
            ->openUrlInNewTab();
    }

    public static function sendStatement(): Action
    {
        return Action::make('sendStatement')
            ->label('Enviar estado por WhatsApp')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('gray')
            ->visible(fn (Model $record) => filled(self::loan($record)->client->routeNotificationForWaha()) && app(WahaClient::class)->enabled())
            ->requiresConfirmation()
            ->action(function (Model $record) {
                $loan = self::loan($record);
                $text = "Hola {$loan->client->firstName()} 👋\n\nTe comparto el estado de cuenta de tu préstamo. Saldo actual: *".Money::cop($loan->balance)."*.\n\n📄 {$loan->statementUrl()}\n— {$loan->user->displayName()}";
                try {
                    app(WahaClient::class)->sendText($loan->client->phone, $text);
                    \App\Models\MessageLog::create(['user_id' => $loan->user_id, 'client_id' => $loan->client_id, 'loan_id' => $loan->id, 'type' => 'Estado de cuenta', 'body' => $text]);
                    Notification::make()->success()->title('Estado de cuenta enviado')->send();
                } catch (Throwable $e) {
                    Notification::make()->danger()->title('No se pudo enviar')->body($e->getMessage())->send();
                }
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Cancelar préstamo')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (Model $record) => self::open($record))
            ->requiresConfirmation()
            ->modalDescription('El préstamo deja de generar recordatorios y mora. Úsalo para acuerdos de cierre o casos perdidos.')
            ->action(function (Model $record) {
                self::loan($record)->update(['status' => LoanStatus::Cancelled]);
                Notification::make()->success()->title('Préstamo cancelado')->send();
            });
    }

    public static function reopen(): Action
    {
        return Action::make('reopen')
            ->label('Reactivar')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->visible(fn (Model $record) => self::loan($record)->status === LoanStatus::Cancelled)
            ->requiresConfirmation()
            ->action(function (Model $record) {
                $loan = self::loan($record);
                $loan->update(['status' => LoanStatus::Active]);
                app(LoanLedger::class)->recalculate($loan);
            });
    }
}
