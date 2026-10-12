<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    public function getSubheading(): ?string
    {
        $s = $this->getRecord()->paymentScore();

        return $s ? "Cumplimiento: {$s['label']} ({$s['score']}% de cuotas pagadas a tiempo)" : 'Todavía sin historial de pagos';
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn () => ! $this->getRecord()->loans()->whereIn('status', ['activo', 'atrasado'])->exists()),
        ];
    }
}
