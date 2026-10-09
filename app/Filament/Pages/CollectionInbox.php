<?php

namespace App\Filament\Pages;

use App\Enums\PromiseStatus;
use App\Enums\ScheduleStatus;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Support\LoanActions;
use App\Filament\Support\Money;
use App\Models\LoanSchedule;
use App\Notifications\InstallmentOverdue;
use App\Notifications\InstallmentReminder;
use App\Services\Waha\WahaClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;
use UnitEnum;

/** La pantalla del día: a quién cobrarle, cuánto y con qué acción. */
class CollectionInbox extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.collection-inbox';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|UnitEnum|null $navigationGroup = 'Cobranza';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Bandeja de cobro';

    protected static ?string $title = 'Bandeja de cobro';

    public static function getNavigationBadge(): ?string
    {
        $n = static::baseQuery()->whereDate('scheduled_date', '<=', today())->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    protected static function baseQuery(): Builder
    {
        return LoanSchedule::query()
            ->where('kind', 'installment')
            ->whereIn('status', [ScheduleStatus::Pending, ScheduleStatus::Overdue])
            ->whereHas('loan', fn (Builder $q) => $q->where('user_id', auth()->id())->whereIn('status', ['activo', 'atrasado']));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(static::baseQuery()->with(['loan.client', 'loan.promises' => fn ($q) => $q->where('status', PromiseStatus::Pending)]))
            ->defaultSort('scheduled_date')
            ->recordUrl(fn (LoanSchedule $record) => LoanResource::getUrl('view', ['record' => $record->loan_id]))
            ->columns([
                TextColumn::make('loan.client.name')->label('Cliente')->weight('bold')->searchable()
                    ->description(fn (LoanSchedule $record) => $record->loan->client->phone),
                TextColumn::make('scheduled_date')->label('Vence')->date('d M')->sortable()
                    ->description(fn (LoanSchedule $record) => match (true) {
                        $record->scheduled_date->isToday() => 'Hoy',
                        $record->scheduled_date->isTomorrow() => 'Mañana',
                        $record->scheduled_date->isPast() => $record->daysOverdue().' días de atraso',
                        default => $record->scheduled_date->diffForHumans(),
                    })
                    ->color(fn (LoanSchedule $record) => $record->status === ScheduleStatus::Overdue ? 'danger' : null),
                TextColumn::make('amount_pending')->label('Por cobrar')->alignEnd()->weight('bold')
                    ->state(fn (LoanSchedule $record) => $record->amount_pending)->formatStateUsing(fn ($state) => Money::cop($state)),
                TextColumn::make('promise')->label('Promesa')->badge()->color('warning')->placeholder('—')
                    ->state(fn (LoanSchedule $record) => ($p = $record->loan->promises->first())
                        ? Money::cop($p->amount).' el '.$p->promised_date->translatedFormat('j M') : null),
                TextColumn::make('status')->label('Estado')->badge(),
            ])
            ->filters([
                SelectFilter::make('when')->label('Mostrar')
                    ->options(['today' => 'Hoy y vencidas', 'overdue' => 'Solo vencidas', 'week' => 'Próximos 7 días', 'all' => 'Todas las pendientes'])
                    ->default('today')
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? 'today') {
                        'overdue' => $query->where('status', ScheduleStatus::Overdue),
                        'week' => $query->whereDate('scheduled_date', '<=', today()->addDays(7)),
                        'all' => $query,
                        default => $query->whereDate('scheduled_date', '<=', today()),
                    }),
            ])
            ->recordActions([
                LoanActions::registerPayment()->iconButton(),
                Action::make('remind')->label('Enviar recordatorio')->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)->iconButton()->color('info')
                    ->visible(fn (LoanSchedule $record) => filled($record->loan->client->routeNotificationForWaha()) && app(WahaClient::class)->enabled())
                    ->requiresConfirmation()
                    ->modalDescription('Se envía ahora por WhatsApp con tu plantilla de mensaje.')
                    ->action(function (LoanSchedule $record) {
                        try {
                            $record->loan->client->notifyNow($record->status === ScheduleStatus::Overdue
                                ? new InstallmentOverdue($record) : new InstallmentReminder($record));
                            Notification::make()->success()->title('Recordatorio enviado')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('No se pudo enviar')->body($e->getMessage())->send();
                        }
                    }),
                ActionGroup::make([
                    LoanActions::promise(),
                    Action::make('call')->label('Llamar')->icon(Heroicon::OutlinedPhone)
                        ->visible(fn (LoanSchedule $record) => filled($record->loan->client->phone))
                        ->url(fn (LoanSchedule $record) => 'tel:'.preg_replace('/\D/', '', $record->loan->client->phone)),
                    Action::make('whatsapp')->label('Abrir chat de WhatsApp')->icon(Heroicon::OutlinedChatBubbleOvalLeft)
                        ->visible(fn (LoanSchedule $record) => filled($record->loan->client->phone))
                        ->url(fn (LoanSchedule $record) => 'https://wa.me/'.(strlen($d = preg_replace('/\D/', '', $record->loan->client->phone)) === 10 ? '57'.$d : $d), true),
                    LoanActions::restructure(),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedFaceSmile)
            ->emptyStateHeading('Nada por cobrar hoy')
            ->emptyStateDescription('Todos tus clientes están al día.');
    }
}
