<?php

namespace App\Filament\Pages;

use App\Services\MessageTemplates;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use UnitEnum;

class LenderSettings extends Page
{
    protected string $view = 'filament.pages.lender-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Mi configuración';

    protected static ?string $title = 'Configuración';

    public ?array $data = [];

    public function mount(): void
    {
        $user = auth()->user();
        $this->form->fill([
            'phone' => $user->phone,
            'notifications_enabled' => $user->notifications_enabled,
            'webhook_url' => $user->webhook_url,
            'settings' => array_replace_recursive([
                'business_name' => null,
                'late_fee' => ['enabled' => false, 'percent' => 5, 'grace_days' => 3],
                'templates' => array_fill_keys(array_keys(MessageTemplates::DEFAULTS), null),
            ], $user->settings ?? []),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $vars = 'Variables: {nombre} {monto} {fecha} {saldo} {dias} {prestamista} {enlace}. Vacío = texto sugerido.';

        return $schema->statePath('data')->components([
            Form::make([
                Section::make('Tu negocio')->columns(2)->schema([
                    TextInput::make('settings.business_name')->label('Nombre que ven tus clientes')
                        ->placeholder(auth()->user()->name)->helperText('Firma de los WhatsApp, recibos y estados de cuenta.'),
                    TextInput::make('phone')->label('Tu WhatsApp')->tel()->helperText('Aquí te llega el resumen diario. Tus clientes lo ven en su portal.'),
                    Toggle::make('notifications_enabled')->label('Enviar recordatorios automáticos a mis clientes')->columnSpanFull(),
                ]),
                Section::make('Recargo por mora')->columns(3)
                    ->description('Se cobra una sola vez por cuota, cuando pasa de los días de gracia. Puedes perdonarlo desde el préstamo.')
                    ->schema([
                        Toggle::make('settings.late_fee.enabled')->label('Cobrar mora')->live()->columnSpanFull(),
                        TextInput::make('settings.late_fee.percent')->label('% sobre la cuota vencida')->numeric()->suffix('%')->minValue(0)->maxValue(50)
                            ->visible(fn (Get $get) => (bool) $get('settings.late_fee.enabled')),
                        TextInput::make('settings.late_fee.grace_days')->label('Días de gracia')->numeric()->minValue(0)->maxValue(60)
                            ->visible(fn (Get $get) => (bool) $get('settings.late_fee.enabled')),
                    ]),
                Section::make('Mensajes de WhatsApp')->description($vars)->collapsible()->collapsed()->schema(
                    collect(MessageTemplates::LABELS)->map(fn ($label, $key) => Textarea::make("settings.templates.{$key}")
                        ->label($label)->rows(5)->placeholder(MessageTemplates::DEFAULTS[$key]))->values()->all()
                ),
                Section::make('Integración (webhook)')->collapsible()->collapsed()
                    ->description('Envía cada desembolso y pago a otro sistema, por ejemplo afdeveloper.com → Finanzas.')
                    ->schema([
                        TextInput::make('webhook_url')->label('URL')->url()->placeholder('https://afdeveloper.com/webhooks/creditrack'),
                    ]),
            ])->livewireSubmitHandler('save')->footer([
                Actions::make([
                    Action::make('save')->label('Guardar')->submit('save')->keyBindings(['mod+s']),
                    Action::make('secret')->label('Generar secreto del webhook')->color('gray')->requiresConfirmation()
                        ->modalDescription('El secreto anterior deja de funcionar. Cópialo en el sistema que recibe los eventos.')
                        ->action(function () {
                            $secret = Str::random(48);
                            auth()->user()->forceFill(['webhook_secret' => $secret])->save();
                            Notification::make()->success()->persistent()->title('Secreto generado (cópialo ahora)')->body($secret)->send();
                        }),
                ]),
            ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $user = auth()->user();
        $user->fill([
            'phone' => $data['phone'] ?? null,
            'notifications_enabled' => (bool) ($data['notifications_enabled'] ?? false),
            'webhook_url' => $data['webhook_url'] ?? null,
            'settings' => array_replace_recursive($user->settings ?? [], $data['settings'] ?? []),
        ])->save();

        Notification::make()->success()->title('Configuración guardada')->send();
    }
}
