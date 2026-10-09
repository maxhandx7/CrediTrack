<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\RelationManagers\LoansRelationManager;
use App\Filament\Resources\Clients\RelationManagers\MessagesRelationManager;
use App\Filament\Support\Money;
use App\Filament\Support\ScopedToLender;
use App\Models\Client;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class ClientResource extends Resource
{
    use ScopedToLender;

    protected static ?string $model = Client::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Cartera';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'cliente';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'document', 'phone'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Datos del cliente')->columnSpan(2)->columns(2)->schema([
                TextInput::make('name')->label('Nombre completo')->required()->maxLength(100)->columnSpanFull(),
                TextInput::make('document')->label('Cédula')->required()->maxLength(20)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('user_id', auth()->id()))
                    ->helperText('Con ella entra al portal de clientes.'),
                TextInput::make('phone')->label('WhatsApp / celular')->tel()->maxLength(20),
                TextInput::make('email')->label('Correo')->email(),
                TextInput::make('address')->label('Dirección'),
                Toggle::make('whatsapp_opt_in')->label('Recibe recordatorios por WhatsApp')->default(true)->columnSpanFull(),
            ]),
            Section::make('Crédito')->columnSpan(1)->schema([
                TextInput::make('credit_limit')->label('Cupo máximo')->numeric()->prefix('$')
                    ->helperText('Opcional: te avisa si un préstamo nuevo lo supera.'),
                Textarea::make('notes')->label('Notas internas')->rows(4),
            ]),
            Section::make('Codeudor')->columnSpan(2)->columns(3)->collapsible()->schema([
                TextInput::make('cosigner_name')->label('Nombre'),
                TextInput::make('cosigner_document')->label('Cédula'),
                TextInput::make('cosigner_phone')->label('Teléfono')->tel(),
            ]),
            Section::make('Documentos')->columnSpan(1)->collapsible()->schema([
                FileUpload::make('documents')->hiddenLabel()->multiple()->disk('local')->directory('clientes')
                    ->visibility('private')->acceptedFileTypes(['image/*', 'application/pdf'])->maxSize(5120)
                    ->openable()->downloadable()->helperText('Cédula, pagaré, recibos de servicios…'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(['loans as open_loans' => fn (Builder $q) => $q->whereIn('status', ['activo', 'atrasado'])])
                ->withSum(['loans as total_lent' => fn (Builder $q) => $q->whereIn('status', ['activo', 'atrasado'])], 'total_amount'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Cliente')->searchable(['name', 'document'])->sortable()->weight('bold')
                    ->description(fn (Client $record) => 'C.C. '.$record->document),
                TextColumn::make('phone')->label('WhatsApp')->searchable()->placeholder('—')
                    ->url(fn (Client $record) => $record->phone ? 'https://wa.me/'.(strlen($d = preg_replace('/\D/', '', $record->phone)) === 10 ? '57'.$d : $d) : null, true),
                TextColumn::make('score')->label('Cumplimiento')->badge()
                    ->state(fn (Client $record) => ($s = $record->paymentScore()) ? "{$s['label']} · {$s['score']}%" : 'Sin historial')
                    ->color(fn (Client $record) => $record->paymentScore()['color'] ?? 'gray'),
                TextColumn::make('open_loans')->label('Préstamos activos')->alignCenter()->sortable(),
                TextColumn::make('total_lent')->label('Cartera activa')->alignEnd()->sortable()
                    ->formatStateUsing(fn ($state) => Money::cop($state))->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('with_open_loans')->label('Con préstamos activos')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('loans', fn ($q) => $q->whereIn('status', ['activo', 'atrasado'])),
                        false: fn (Builder $query) => $query->whereDoesntHave('loans', fn ($q) => $q->whereIn('status', ['activo', 'atrasado'])),
                    ),
            ])
            ->recordActions([
                Action::make('newLoan')->label('Prestar')->icon(Heroicon::OutlinedPlusCircle)->iconButton()
                    ->tooltip('Nuevo préstamo para este cliente')
                    ->url(fn (Client $record) => \App\Filament\Resources\Loans\LoanResource::getUrl('create', ['client_id' => $record->id])),
                EditAction::make()->iconButton(),
            ]);
    }

    public static function getRelations(): array
    {
        return [LoansRelationManager::class, MessagesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'edit' => EditClient::route('/{record}/edit'),
        ];
    }
}
