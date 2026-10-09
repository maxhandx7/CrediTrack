<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Resources\Loans\LoanResource;
use App\Models\Client;
use App\Services\LoanLedger;
use App\Services\ScheduleGenerator;
use App\Services\Webhooks\WebhookDispatcher;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateLoan extends CreateRecord
{
    protected static string $resource = LoanResource::class;

    /** Permite llegar desde el simulador o desde un cliente con los datos ya llenos. */
    protected function fillForm(): void
    {
        parent::fillForm();

        $prefill = array_filter(request()->only(['client_id', 'amount', 'interest_rate', 'interest_type', 'payment_frequency', 'start_date', 'due_date']));
        if ($prefill !== []) {
            $this->form->fill(array_merge($this->form->getRawState(), $prefill));
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $client = Client::where('user_id', auth()->id())->findOrFail($data['client_id']);

        if ($client->credit_limit && (float) $data['amount'] > (float) $client->credit_limit) {
            Notification::make()->warning()->title('Supera el cupo del cliente')
                ->body('Cupo configurado: $'.number_format((float) $client->credit_limit, 0, ',', '.'))->send();
        }

        return $data + ['user_id' => auth()->id(), 'status' => 'activo'];
    }

    protected function afterCreate(): void
    {
        DB::transaction(function () {
            app(ScheduleGenerator::class)->generate($this->record);
            app(LoanLedger::class)->recalculate($this->record);
        });
        app(WebhookDispatcher::class)->loanCreated($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return LoanResource::getUrl('view', ['record' => $this->record]);
    }
}
