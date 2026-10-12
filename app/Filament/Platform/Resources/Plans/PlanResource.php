<?php

namespace App\Filament\Platform\Resources\Plans;

use App\Filament\Platform\Resources\Plans\Pages\ManagePlans;
use App\Filament\Support\Money;
use App\Models\Plan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Negocio';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'plan';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('name')->label('Nombre')->required(),
            TextInput::make('slug')->label('Identificador')->required()->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('price_monthly')->label('Precio mensual')->numeric()->prefix('$')->required(),
            TextInput::make('max_active_loans')->label('Máx. préstamos activos')->numeric()->helperText('Vacío = ilimitado'),
            TextInput::make('max_users')->label('Máx. personas')->numeric()->helperText('Vacío = ilimitado'),
            TextInput::make('max_daily_messages')->label('WhatsApp al día')->numeric(),
            Section::make('Funciones')->columns(3)->columnSpanFull()->schema([
                Toggle::make('features.branding')->label('Logo propio'),
                Toggle::make('features.webhook')->label('Webhook'),
                Toggle::make('features.own_whatsapp')->label('Número de WhatsApp propio'),
            ]),
            TextInput::make('sort')->label('Orden')->numeric()->default(0),
            Toggle::make('is_active')->label('Disponible')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->reorderable('sort')->columns([
            TextColumn::make('name')->label('Plan')->weight('bold'),
            TextColumn::make('price_monthly')->label('Precio / mes')->formatStateUsing(fn ($state) => Money::cop($state)),
            TextColumn::make('max_active_loans')->label('Préstamos')->placeholder('∞'),
            TextColumn::make('max_users')->label('Personas')->placeholder('∞'),
            TextColumn::make('companies_count')->label('Empresas')->counts('companies'),
            ToggleColumn::make('is_active')->label('Disponible'),
        ])->recordActions([EditAction::make()->iconButton()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePlans::route('/')];
    }
}
