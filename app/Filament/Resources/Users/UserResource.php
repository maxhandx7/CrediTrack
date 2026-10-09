<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
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

/** Prestamistas con acceso (solo lo ve el administrador). */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'prestamista';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nombre')->required(),
            TextInput::make('email')->label('Correo')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('phone')->label('WhatsApp')->tel(),
            TextInput::make('password')->label('Contraseña')->password()->revealable()
                ->required(fn (?User $record) => $record === null)->dehydrated(fn ($state) => filled($state))->minLength(8),
            Select::make('role')->label('Rol')->options(['user' => 'Prestamista', 'admin' => 'Administrador'])->default('user')->required(),
            Select::make('status')->label('Estado')->options(['active' => 'Activo', 'inactive' => 'Inactivo'])->default('active')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Nombre')->searchable()->description(fn (User $record) => $record->email),
            TextColumn::make('role')->label('Rol')->badge()->formatStateUsing(fn ($state) => $state === 'admin' ? 'Administrador' : 'Prestamista'),
            TextColumn::make('status')->label('Estado')->badge()
                ->formatStateUsing(fn ($state) => $state === 'inactive' ? 'Inactivo' : 'Activo')
                ->color(fn ($state) => $state === 'inactive' ? 'gray' : 'success'),
            TextColumn::make('loans_count')->label('Préstamos')->counts('loans'),
        ])->recordActions([EditAction::make()->iconButton()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
