<?php

namespace App\Filament\Resources\Loans\RelationManagers;

use App\Enums\ScheduleStatus;
use App\Filament\Support\Money;
use App\Models\LoanSchedule;
use App\Services\LateFees;
use App\Services\LoanLedger;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'schedules';

    protected static ?string $title = 'Cuotas';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                TextColumn::make('scheduled_date')->label('Fecha')->date('d M Y')
                    ->description(fn (LoanSchedule $record) => $record->note),
                TextColumn::make('kind')->label('Concepto')->badge()
                    ->formatStateUsing(fn ($state) => $state === 'fee' ? 'Mora' : 'Cuota')
                    ->color(fn ($state) => $state === 'fee' ? 'warning' : 'gray'),
                TextColumn::make('amount_due')->label('Valor')->alignEnd()->formatStateUsing(fn ($state) => Money::cop($state)),
                TextColumn::make('amount_paid')->label('Abonado')->alignEnd()->formatStateUsing(fn ($state) => Money::cop($state)),
                TextColumn::make('amount_pending')->label('Pendiente')->alignEnd()->weight('bold')
                    ->state(fn (LoanSchedule $record) => $record->amount_pending)->formatStateUsing(fn ($state) => Money::cop($state)),
                TextColumn::make('status')->label('Estado')->badge()
                    ->description(fn (LoanSchedule $record) => $record->status === ScheduleStatus::Overdue ? $record->daysOverdue().' días' : null),
            ])
            ->recordActions([
                Action::make('waive')->label('Perdonar mora')->icon(Heroicon::OutlinedGift)->color('warning')
                    ->visible(fn (LoanSchedule $record) => $record->isFee() && $record->amount_pending > 0)
                    ->requiresConfirmation()
                    ->action(function (LoanSchedule $record) {
                        app(LateFees::class)->waive($record);
                        Notification::make()->success()->title('Recargo perdonado')->send();
                    }),
                Action::make('reschedule')->label('Mover fecha')->icon(Heroicon::OutlinedCalendarDays)->color('gray')
                    ->visible(fn (LoanSchedule $record) => $record->status !== ScheduleStatus::Paid)
                    ->schema([DatePicker::make('scheduled_date')->label('Nueva fecha')->native(false)->required()])
                    ->fillForm(fn (LoanSchedule $record) => ['scheduled_date' => $record->scheduled_date])
                    ->action(function (LoanSchedule $record, array $data) {
                        $record->update(['scheduled_date' => $data['scheduled_date'], 'note' => trim(($record->note ? $record->note.' · ' : '').'movida desde el '.$record->scheduled_date->format('d/m'))]);
                        app(LoanLedger::class)->recalculate($record->loan()->first());
                        Notification::make()->success()->title('Fecha actualizada')->send();
                    }),
            ]);
    }
}
