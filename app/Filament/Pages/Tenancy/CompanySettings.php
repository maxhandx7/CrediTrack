<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Company;
use App\Services\MessageTemplates;
use App\Support\Access;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Configuración de la empresa (solo el dueño): marca, privacidad, avisos, mora, mensajes, integraciones. */
class CompanySettings extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Configuración de la empresa';
    }

    public static function canView(Model $tenant): bool
    {
        return Access::role(auth()->user(), $tenant) === 'owner';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['settings'] = array_replace_recursive(Company::DEFAULTS, $data['settings'] ?? []);
        $data['settings']['notify']['overdue_days'] = array_values(array_filter(explode(',', (string) $data['settings']['notify']['overdue_days'])));

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $days = (array) ($data['settings']['notify']['overdue_days'] ?? []);
        sort($days, SORT_NUMERIC);
        $data['settings']['notify']['overdue_days'] = implode(',', $days);
        // Se fusiona con lo guardado para no perder opciones ocultas por el plan (ej. la sesión de WhatsApp).
        $data['settings'] = array_replace_recursive($this->tenant->settings ?? [], $data['settings']);

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        $company = $this->tenant;

        return $schema->components([
            Tabs::make()->persistTabInQueryString()->tabs([
                Tab::make('Empresa')->icon('heroicon-o-building-storefront')->schema([
                    Section::make()->columns(2)->schema([
                        TextInput::make('name')->label('Nombre del negocio')->required()->maxLength(80),
                        TextInput::make('phone')->label('WhatsApp del negocio')->tel()->helperText('Recibe el resumen diario; tus clientes lo ven para escribirte.'),
                        FileUpload::make('logo')->label('Logo')->image()->disk('local')->directory('logos')->visibility('private')->maxSize(1024)
                            ->visible($company->hasFeature('branding'))
                            ->helperText('Aparece en el portal de tus clientes y en los PDF.'),
                    ]),
                ]),
                Tab::make('Portal del cliente')->icon('heroicon-o-device-phone-mobile')->schema([
                    Section::make('¿Qué pueden ver tus clientes en /mi-cuenta?')->schema([
                        Toggle::make('settings.portal.show_balance')->label('Saldo total y total a pagar')->live()
                            ->helperText('Si lo apagas, el cliente solo ve su próxima cuota. Tampoco verá el saldo en los recibos.'),
                        Toggle::make('settings.portal.show_schedule')->label('Todas las cuotas (si no, solo la próxima)'),
                        Toggle::make('settings.portal.show_payments')->label('Historial de pagos y recibos'),
                        Toggle::make('settings.portal.show_statement')->label('Estado de cuenta en PDF')
                            ->disabled(fn (Get $get) => ! $get('settings.portal.show_balance'))
                            ->helperText('Incluye el saldo, así que se oculta si ocultas el saldo.'),
                        Toggle::make('settings.portal.show_contact')->label('Botón para escribirte por WhatsApp'),
                    ]),
                ]),
                Tab::make('Avisos por WhatsApp')->icon('heroicon-o-bell-alert')->schema([
                    Section::make('Qué se envía')->columns(2)->schema([
                        Toggle::make('settings.notify.enabled')->label('Enviar avisos automáticos')->live()->columnSpanFull(),
                        Toggle::make('settings.notify.reminder_before')->label('Recordatorio la víspera'),
                        Toggle::make('settings.notify.reminder_today')->label('Recordatorio el día del pago'),
                        Toggle::make('settings.notify.overdue')->label('Aviso de cuota vencida')->live(),
                        Toggle::make('settings.notify.promise')->label('Recordatorio de promesa de pago'),
                        Toggle::make('settings.notify.receipt')->label('Recibo al registrar un pago'),
                        Toggle::make('settings.notify.lender_digest')->label('Resumen diario para mí'),
                        CheckboxList::make('settings.notify.overdue_days')->label('Días de atraso en que se avisa')->columnSpanFull()->columns(6)
                            ->options(array_combine($d = ['1', '2', '3', '5', '7', '10', '15', '20', '30', '45', '60', '90'], array_map(fn ($x) => "Día {$x}", $d)))
                            ->visible(fn (Get $get) => (bool) $get('settings.notify.overdue')),
                    ]),
                    Section::make('Qué dicen los mensajes')->schema([
                        Toggle::make('settings.notify.include_balance')->label('Mencionar el saldo total')
                            ->helperText('Si lo apagas, los mensajes hablan solo de la cuota o el abono, nunca del total.'),
                        Toggle::make('settings.notify.include_link')->label('Incluir enlace al recibo / estado de cuenta'),
                    ]),
                    Section::make('Cuándo')->columns(2)->schema([
                        Select::make('settings.notify.send_hour')->label('Hora de envío')->selectablePlaceholder(false)
                            ->options(collect(range(6, 20))->mapWithKeys(fn ($h) => [$h => sprintf('%d:00 %s', $h > 12 ? $h - 12 : $h, $h >= 12 ? 'p. m.' : 'a. m.')])->all()),
                        Toggle::make('settings.notify.skip_sundays')->label('No enviar los domingos')->inline(false),
                    ]),
                ]),
                Tab::make('Mora')->icon('heroicon-o-exclamation-triangle')->schema([
                    Section::make()->columns(3)->description('Se cobra una vez por cuota, sobre lo que falta de ella, cuando pasa de los días de gracia. Se puede perdonar desde el préstamo.')->schema([
                        Toggle::make('settings.late_fee.enabled')->label('Cobrar recargo por mora')->live()->columnSpanFull(),
                        TextInput::make('settings.late_fee.percent')->label('% sobre la cuota vencida')->numeric()->suffix('%')->minValue(0)->maxValue(50)
                            ->visible(fn (Get $get) => (bool) $get('settings.late_fee.enabled')),
                        TextInput::make('settings.late_fee.grace_days')->label('Días de gracia')->numeric()->minValue(0)->maxValue(60)
                            ->visible(fn (Get $get) => (bool) $get('settings.late_fee.enabled')),
                    ]),
                ]),
                Tab::make('Mensajes')->icon('heroicon-o-chat-bubble-left-right')->schema([
                    Section::make()->description('Variables: {nombre} {monto} {fecha} {saldo} {dias} {prestamista} {enlace}. Lo que pongas entre [[ ]] se omite si esa variable está oculta. Vacío = texto sugerido.')
                        ->schema(collect(MessageTemplates::LABELS)->map(fn ($label, $key) => Textarea::make("settings.templates.{$key}")
                            ->label($label)->rows(5)->placeholder(MessageTemplates::DEFAULTS[$key]))->values()->all()),
                ]),
                Tab::make('Integraciones')->icon('heroicon-o-puzzle-piece')->schema([
                    Section::make('Número de WhatsApp propio')->visible($company->hasFeature('own_whatsapp'))
                        ->description('Por defecto los mensajes salen del número de CrediTrack, firmados con tu nombre. Con tu propio número, escribe aquí el nombre de tu sesión de WhatsApp (te la entrega soporte).')
                        ->schema([TextInput::make('settings.whatsapp.session')->label('Sesión de WhatsApp')->placeholder('session_3001234567')]),
                    Section::make('Webhook')->visible($company->hasFeature('webhook'))
                        ->description('Envía cada desembolso y pago a otro sistema (contabilidad, ERP, afdeveloper.com…).')
                        ->schema([TextInput::make('webhook_url')->label('URL')->url()]),
                    Section::make()->visible(! $company->hasFeature('webhook') && ! $company->hasFeature('own_whatsapp'))
                        ->schema([\Filament\Schemas\Components\Text::make('Las integraciones (webhook y número de WhatsApp propio) están disponibles en planes superiores. Revisa "Suscripción".')]),
                ]),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('secret')->label('Generar secreto del webhook')->color('gray')
                ->visible($this->tenant->hasFeature('webhook'))->requiresConfirmation()
                ->modalDescription('El secreto anterior deja de funcionar. Cópialo en el sistema que recibe los eventos.')
                ->action(function () {
                    $secret = Str::random(48);
                    $this->tenant->forceFill(['webhook_secret' => $secret])->save();
                    Notification::make()->success()->persistent()->title('Secreto generado (cópialo ahora)')->body($secret)->send();
                }),
        ];
    }
}
