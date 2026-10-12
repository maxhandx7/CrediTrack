<?php

namespace App\Filament\Platform\Resources\Companies;

use App\Filament\Platform\Resources\Companies\Pages\EditCompany;
use App\Filament\Platform\Resources\Companies\Pages\ListCompanies;
use App\Filament\Support\Money;
use App\Models\Company;
use App\Models\Plan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Negocio';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'empresa';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canCreate(): bool
    {
        return false; // las empresas se registran solas (/admin/register)
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Empresa')->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->label('Nombre')->required(),
                TextInput::make('phone')->label('WhatsApp')->tel(),
            ]),
            Section::make('Suscripción')->columns(4)->columnSpanFull()->schema([
                Select::make('plan_id')->label('Plan')->relationship('plan', 'name')->preload(),
                Select::make('status')->label('Estado')->required()
                    ->options(['trial' => 'Prueba gratis', 'active' => 'Activa', 'past_due' => 'Vencida', 'suspended' => 'Suspendida']),
                DatePicker::make('trial_ends_at')->label('Fin de la prueba')->native(false),
                DatePicker::make('paid_until')->label('Pagada hasta')->native(false)->helperText('Vacío y activa = sin vencimiento.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['plan', 'owners'])
                ->withCount(['loans as active_loans' => fn ($l) => $l->whereIn('status', ['activo', 'atrasado'])])
                ->withCount('users'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Empresa')->searchable()->weight('bold')
                    ->description(fn (Company $r) => $r->owners->first()?->email),
                TextColumn::make('plan.name')->label('Plan')->badge()->color('gray'),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn ($state, Company $r) => $r->statusLabel())
                    ->color(fn (Company $r) => $r->statusColor())
                    ->description(fn (Company $r) => ($d = $r->daysLeft()) === null ? 'Sin vencimiento' : ($d >= 0 ? "Quedan {$d} día(s)" : 'Venció hace '.abs($d).' día(s)')),
                TextColumn::make('active_loans')->label('Préstamos activos')->alignCenter()->sortable(),
                TextColumn::make('users_count')->label('Usuarios')->alignCenter(),
                TextColumn::make('created_at')->label('Registro')->date('d M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(['trial' => 'Prueba', 'active' => 'Activa', 'past_due' => 'Vencida', 'suspended' => 'Suspendida']),
                SelectFilter::make('plan')->label('Plan')->relationship('plan', 'name'),
            ])
            ->recordActions([
                Action::make('payment')->label('Registrar pago')->icon(Heroicon::OutlinedBanknotes)->color('success')->iconButton()
                    ->modalHeading(fn (Company $r) => "Pago de suscripción — {$r->name}")
                    ->schema(fn (Company $r) => [
                        Select::make('plan_id')->label('Plan')->options(Plan::where('is_active', true)->orderBy('sort')->pluck('name', 'id'))
                            ->default($r->plan_id)->required()->live()
                            ->afterStateUpdated(fn ($state, $set, $get) => $set('amount', (float) Plan::find($state)?->price_monthly * (int) $get('months'))),
                        TextInput::make('months')->label('Meses')->numeric()->minValue(1)->maxValue(24)->default(1)->required()->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set, $get) => $set('amount', (float) Plan::find($get('plan_id'))?->price_monthly * (int) $state)),
                        TextInput::make('amount')->label('Valor recibido')->numeric()->prefix('$')->required()->default((float) $r->plan?->price_monthly),
                        TextInput::make('reference')->label('Referencia / comprobante'),
                    ])
                    ->action(function (Company $r, array $data) {
                        $p = $r->registerSubscriptionPayment((float) $data['amount'], (int) $data['months'], (int) $data['plan_id'], 'manual', $data['reference'] ?? null, auth()->id());
                        Notification::make()->success()->title("{$r->name}: activa hasta el ".$p->period_end->format('d/m/Y'))->send();
                    }),
                ActionGroup::make([
                    Action::make('extendTrial')->label('Extender prueba')->icon(Heroicon::OutlinedClock)
                        ->schema([TextInput::make('days')->label('Días adicionales')->numeric()->default(7)->minValue(1)->required()])
                        ->action(fn (Company $r, array $data) => $r->update([
                            'status' => 'trial',
                            'trial_ends_at' => ($r->trial_ends_at?->isFuture() ? $r->trial_ends_at : today())->copy()->addDays((int) $data['days']),
                        ])),
                    Action::make('suspend')->label('Suspender')->icon(Heroicon::OutlinedNoSymbol)->color('danger')->requiresConfirmation()
                        ->visible(fn (Company $r) => $r->status !== 'suspended')
                        ->modalDescription('La empresa no podrá usar el panel ni enviará mensajes. Sus datos se conservan.')
                        ->action(fn (Company $r) => $r->update(['status' => 'suspended'])),
                    Action::make('reactivate')->label('Reactivar')->icon(Heroicon::OutlinedCheckCircle)
                        ->visible(fn (Company $r) => $r->status === 'suspended')
                        ->action(fn (Company $r) => $r->update(['status' => 'active'])),
                    Action::make('enter')->label('Entrar al panel de la empresa')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (Company $r) => Filament::getPanel('admin')->getUrl($r), true),
                    EditAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\PaymentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListCompanies::route('/'), 'edit' => EditCompany::route('/{record}/edit')];
    }
}
