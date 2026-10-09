<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\ActivityLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Bitácora: quién creó, cambió o borró qué. */
class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'registro';

    protected static ?string $navigationLabel = 'Bitácora';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('user');

        return auth()->user()?->isAdmin() ? $query : $query->where('user_id', auth()->id());
    }

    public static function table(Table $table): Table
    {
        $labels = ['Loan' => 'Préstamo', 'Payment' => 'Pago', 'Client' => 'Cliente', 'PaymentPromise' => 'Promesa', 'User' => 'Usuario'];

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('d M Y, h:i a')->sortable(),
                TextColumn::make('user.name')->label('Usuario')->placeholder('Sistema'),
                TextColumn::make('event')->label('Acción')->badge()
                    ->formatStateUsing(fn ($state) => ['created' => 'Creó', 'updated' => 'Editó', 'deleted' => 'Borró'][$state] ?? $state)
                    ->color(fn ($state) => ['created' => 'success', 'updated' => 'info', 'deleted' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('subject_type')->label('Qué')->formatStateUsing(fn ($state, ActivityLog $record) => ($labels[$state] ?? $state).' #'.$record->subject_id),
                TextColumn::make('changes')->label('Cambios')->wrap()->limit(140)->placeholder('—')
                    ->formatStateUsing(fn ($state) => collect(is_array($state) ? $state : json_decode((string) $state, true) ?? [])
                        ->map(fn ($v, $k) => "{$k}: ".(is_scalar($v) || $v === null ? var_export($v, true) : json_encode($v)))->implode(' · ')),
            ])
            ->filters([
                SelectFilter::make('subject_type')->label('Tipo')->options($labels),
                SelectFilter::make('event')->label('Acción')->options(['created' => 'Creó', 'updated' => 'Editó', 'deleted' => 'Borró']),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListActivityLogs::route('/')];
    }
}
