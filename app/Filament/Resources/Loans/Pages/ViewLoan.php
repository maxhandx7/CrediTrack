<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Support\LoanActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLoan extends ViewRecord
{
    protected static string $resource = LoanResource::class;

    public function getTitle(): string
    {
        return 'Préstamo #'.$this->record->id.' · '.$this->record->client->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            LoanActions::registerPayment(),
            LoanActions::promise(),
            LoanActions::restructure(),
            ActionGroup::make([
                LoanActions::statement(),
                LoanActions::sendStatement(),
                EditAction::make(),
                LoanActions::cancel(),
                LoanActions::reopen(),
            ])->label('Más')->button()->color('gray'),
        ];
    }
}
