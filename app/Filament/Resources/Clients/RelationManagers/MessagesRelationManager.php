<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Models\MessageLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'WhatsApp enviados';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('d M Y, h:i a')->sortable(),
                TextColumn::make('type')->label('Tipo')->badge()->color('gray'),
                TextColumn::make('body')->label('Mensaje')->limit(120)->wrap()->tooltip(fn (MessageLog $record) => $record->body),
                TextColumn::make('status')->label('')->badge()
                    ->formatStateUsing(fn ($state) => $state === 'sent' ? 'Enviado' : 'Falló')
                    ->color(fn ($state) => $state === 'sent' ? 'success' : 'danger')
                    ->tooltip(fn (MessageLog $record) => $record->error),
            ]);
    }
}
