<?php

namespace App\Filament\Platform\Resources\Companies\Pages;

use App\Filament\Platform\Resources\Companies\CompanyResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCompanies extends ListRecords
{
    protected static string $resource = CompanyResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todas'),
            'trial' => Tab::make('En prueba')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'trial')),
            'active' => Tab::make('Activas')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active')),
            'attention' => Tab::make('Vencidas o suspendidas')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['past_due', 'suspended'])),
        ];
    }
}
