<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Resources\Loans\LoanResource;
use App\Services\LoanLedger;
use App\Services\ScheduleGenerator;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLoan extends EditRecord
{
    protected static string $resource = LoanResource::class;

    private const MONEY = ['amount', 'interest_rate', 'interest_type', 'start_date', 'due_date', 'payment_frequency'];

    protected function afterSave(): void
    {
        // Sin pagos se pueden cambiar las condiciones: se regenera el cronograma.
        if ($this->record->wasChanged(self::MONEY) && ! $this->record->validPayments()->exists()) {
            app(ScheduleGenerator::class)->generate($this->record);
        }
        app(LoanLedger::class)->recalculate($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return LoanResource::getUrl('view', ['record' => $this->record]);
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->visible(fn () => ! $this->record->payments()->exists())];
    }
}
