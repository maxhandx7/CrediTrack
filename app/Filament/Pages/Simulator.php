<?php

namespace App\Filament\Pages;

use App\Enums\InterestType;
use App\Enums\PaymentFrequency;
use App\Filament\Resources\Loans\LoanResource;
use App\Services\ScheduleGenerator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;
use UnitEnum;

/** Ver el plan de cuotas ANTES de prestar, y crear el préstamo con un clic. */
class Simulator extends Page
{
    protected string $view = 'filament.pages.simulator';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Cartera';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Simulador';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'amount' => 1000000, 'interest_rate' => 10, 'interest_type' => InterestType::Simple->value,
            'payment_frequency' => PaymentFrequency::Biweekly->value,
            'start_date' => today()->toDateString(), 'due_date' => today()->addMonths(3)->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make()->columns(['default' => 2, 'lg' => 6])->schema([
                TextInput::make('amount')->label('Monto')->numeric()->prefix('$')->live(onBlur: true)->columnSpan(2),
                TextInput::make('interest_rate')->label('Interés por periodo')->numeric()->suffix('%')->live(onBlur: true),
                Select::make('interest_type')->label('Tipo')->options(InterestType::class)->live()->selectablePlaceholder(false),
                Select::make('payment_frequency')->label('Frecuencia')->options(PaymentFrequency::class)->live()->selectablePlaceholder(false)->columnSpan(2),
                DatePicker::make('start_date')->label('Desembolso')->native(false)->live()->columnSpan(['default' => 1, 'lg' => 3]),
                DatePicker::make('due_date')->label('Fecha final')->native(false)->live()->columnSpan(['default' => 1, 'lg' => 3]),
            ]),
        ]);
    }

    /** @return array{total: float, installments: list<array{date: string, amount: float}>}|null */
    public function plan(): ?array
    {
        try {
            $d = $this->data;
            $enum = fn ($v, string $class) => $v instanceof BackedEnum ? $v : $class::from($v);

            return app(ScheduleGenerator::class)->plan((float) $d['amount'], (float) $d['interest_rate'],
                $enum($d['interest_type'], InterestType::class), $enum($d['payment_frequency'], PaymentFrequency::class),
                $d['start_date'], $d['due_date']);
        } catch (Throwable) {
            return null;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')->label('Crear préstamo con estos datos')->icon(Heroicon::OutlinedArrowRight)
                ->url(fn () => LoanResource::getUrl('create', collect($this->data)
                    ->map(fn ($v) => $v instanceof BackedEnum ? $v->value : $v)->filter()->all())),
        ];
    }
}
