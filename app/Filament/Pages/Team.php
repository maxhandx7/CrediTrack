<?php

namespace App\Filament\Pages;

use App\Models\Company;
use App\Models\User;
use App\Support\Access;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/** Equipo de la empresa: el dueño agrega prestamistas y cobradores. */
class Team extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.table-page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Equipo';

    public static function canAccess(): bool
    {
        return Access::isOwner();
    }

    private function company(): Company
    {
        return Filament::getTenant();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => User::query()->whereHas('companies', fn ($q) => $q->whereKey($this->company()->id))
                ->with(['companies' => fn ($q) => $q->whereKey($this->company()->id)]))
            ->columns([
                TextColumn::make('name')->label('Nombre')->weight('bold')->description(fn (User $r) => $r->email),
                TextColumn::make('role')->label('Rol')->badge()
                    ->state(fn (User $r) => Company::ROLES[$r->roleIn($this->company())] ?? '—')
                    ->color(fn (User $r) => ['owner' => 'primary', 'lender' => 'info', 'collector' => 'gray'][$r->roleIn($this->company())] ?? 'gray'),
                TextColumn::make('phone')->label('WhatsApp')->placeholder('—'),
            ])
            ->headerActions([
                Action::make('add')->label('Agregar persona')->icon(Heroicon::OutlinedUserPlus)
                    ->schema([
                        TextInput::make('name')->label('Nombre')->required(),
                        TextInput::make('email')->label('Correo')->email()->required(),
                        TextInput::make('password')->label('Contraseña inicial')->password()->revealable()->minLength(8)
                            ->helperText('Solo si el correo no tiene cuenta todavía. Pídele que la cambie al entrar.'),
                        Select::make('role')->label('Rol')->required()->default('collector')
                            ->options(['lender' => 'Prestamista: opera toda la cartera', 'collector' => 'Cobrador: cobra, registra pagos y promesas', 'owner' => 'Dueño: todo, incluida la configuración']),
                    ])
                    ->action(function (array $data) {
                        $company = $this->company();
                        if (! $company->canAddMember()) {
                            Notification::make()->warning()->title('Tu plan no permite más personas')->body('Mejora tu plan en "Suscripción".')->send();

                            return;
                        }
                        $user = User::where('email', $data['email'])->first();
                        if (! $user && blank($data['password'] ?? null)) {
                            Notification::make()->danger()->title('Ese correo no tiene cuenta: escribe una contraseña inicial')->send();

                            return;
                        }
                        $user ??= User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
                        $company->users()->syncWithoutDetaching([$user->id => ['role' => $data['role']]]);
                        Notification::make()->success()->title("{$user->name} agregado como ".Company::ROLES[$data['role']])->send();
                    }),
            ])
            ->recordActions([
                Action::make('role')->label('Cambiar rol')->icon(Heroicon::OutlinedArrowsRightLeft)->iconButton()
                    ->visible(fn (User $r) => $r->id !== auth()->id())
                    ->schema([Select::make('role')->label('Rol')->options(Company::ROLES)->required()])
                    ->fillForm(fn (User $r) => ['role' => $r->roleIn($this->company())])
                    ->action(function (User $r, array $data) {
                        $this->company()->users()->updateExistingPivot($r->id, ['role' => $data['role']]);
                        Notification::make()->success()->title('Rol actualizado')->send();
                    }),
                Action::make('remove')->label('Quitar de la empresa')->icon(Heroicon::OutlinedUserMinus)->iconButton()->color('danger')
                    ->visible(fn (User $r) => $r->id !== auth()->id())
                    ->requiresConfirmation()
                    ->modalDescription('Pierde el acceso a esta empresa. Su cuenta y lo que registró se conservan.')
                    ->action(function (User $r) {
                        DB::transaction(function () use ($r) {
                            $company = $this->company();
                            if ($r->roleIn($company) === 'owner' && $company->owners()->count() <= 1) {
                                Notification::make()->danger()->title('La empresa debe tener al menos un dueño')->send();

                                return;
                            }
                            $company->users()->detach($r->id);
                            Notification::make()->success()->title("{$r->name} ya no tiene acceso")->send();
                        });
                    }),
            ]);
    }
}
