<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Support\Money;
use App\Models\Loan;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LoansRelationManager extends RelationManager
{
    protected static string $relationship = 'loans';

    protected static ?string $title = 'Préstamos';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('start_date', 'desc')
            ->recordUrl(fn (Loan $record) => LoanResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('id')->label('#')->prefix('#'),
                TextColumn::make('start_date')->label('Desde')->date('d M Y'),
                TextColumn::make('amount')->label('Capital')->formatStateUsing(fn ($state) => Money::cop($state)),
                TextColumn::make('total_amount')->label('Total')->formatStateUsing(fn ($state) => Money::cop($state)),
                TextColumn::make('balance')->label('Saldo')->state(fn (Loan $record) => $record->balance)
                    ->formatStateUsing(fn ($state) => Money::cop($state))->weight('bold'),
                TextColumn::make('status')->label('Estado')->badge(),
            ]);
    }
}
