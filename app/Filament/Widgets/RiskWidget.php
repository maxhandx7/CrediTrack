<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Support\Money;
use App\Models\Loan;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Los préstamos que más preocupan: mayor monto vencido. */
class RiskWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Mayor mora';

    public function table(Table $table): Table
    {
        return $table
            ->query(Loan::query()->where('user_id', auth()->id())->where('status', 'atrasado')->with(['client', 'schedules', 'payments']))
            ->paginated(false)
            ->recordUrl(fn (Loan $record) => LoanResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('client.name')->label('Cliente')->weight('bold'),
                TextColumn::make('overdue')->label('Vencido')->alignEnd()->color('danger')
                    ->state(fn (Loan $record) => Money::cop($record->overdueAmount())),
            ])
            ->emptyStateHeading('Sin mora 🎉')
            ->emptyStateDescription('Ningún préstamo atrasado.');
    }
}
