<?php

namespace App\Filament\Resources\Loans\RelationManagers;

use App\Filament\Resources\Promises\PromiseResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class PromisesRelationManager extends RelationManager
{
    protected static string $relationship = 'promises';

    protected static ?string $title = 'Promesas';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return PromiseResource::table($table)->paginated(false);
    }
}
