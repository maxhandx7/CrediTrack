<?php

namespace App\Filament\Platform\Resources\Users;

use App\Filament\Platform\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Todas las cuentas de la plataforma. */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Plataforma';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'usuario';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->is_platform_admin;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nombre')->required(),
            TextInput::make('email')->label('Correo')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('phone')->label('WhatsApp')->tel(),
            TextInput::make('password')->label('Contraseña')->password()->revealable()
                ->required(fn (?User $record) => $record === null)->dehydrated(fn ($state) => filled($state))->minLength(8),
            \Filament\Forms\Components\Toggle::make('is_platform_admin')->label('Administrador de la plataforma')
                ->helperText('Acceso total a /plataforma y a todas las empresas.')
                ->disabled(fn (?User $record) => $record?->is(auth()->user())),
            Select::make('status')->label('Estado')->options(['active' => 'Activo', 'inactive' => 'Inactivo'])->default('active')->required()
                ->disabled(fn (?User $record) => $record?->is(auth()->user()))->helperText('No puedes desactivarte a ti mismo.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Nombre')->searchable()->description(fn (User $record) => $record->email),
            TextColumn::make('companies.name')->label('Empresas')->badge()->color('gray')->limitList(2),
            \Filament\Tables\Columns\IconColumn::make('is_platform_admin')->label('Admin')->boolean(),
            TextColumn::make('status')->label('Estado')->badge()
                ->formatStateUsing(fn ($state) => $state === 'inactive' ? 'Inactivo' : 'Activo')
                ->color(fn ($state) => $state === 'inactive' ? 'gray' : 'success'),

        ])->recordActions([EditAction::make()->iconButton()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
