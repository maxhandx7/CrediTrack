<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasLabel;

enum PaymentFrequency: string implements HasLabel
{
    case Daily = 'diaria';
    case Weekly = 'semanal';
    case Biweekly = 'quincenal';
    case Monthly = 'mensual';

    /** Fecha de la cuota número $n contando desde el inicio (n = 1 es la primera). */
    public function nth(CarbonInterface $start, int $n): CarbonInterface
    {
        return match ($this) {
            self::Daily => $start->copy()->addDays($n),
            self::Weekly => $start->copy()->addWeeks($n),
            self::Biweekly => $start->copy()->addDays(15 * $n),
            self::Monthly => $start->copy()->addMonthsNoOverflow($n), // 31 ene → 28 feb, no 3 mar
        };
    }

    /** Cuántos periodos completos caben entre dos fechas (mínimo 1). */
    public function periodsBetween(CarbonInterface $start, CarbonInterface $end): int
    {
        // Carbon 3 devuelve flotantes con signo: se normaliza aquí, en un solo lugar.
        $periods = match ($this) {
            self::Daily => (int) round($start->diffInDays($end, true)),
            self::Weekly => (int) ceil($start->diffInDays($end, true) / 7),
            self::Biweekly => (int) ceil($start->diffInDays($end, true) / 15),
            self::Monthly => (int) ceil($start->diffInMonths($end, true)),
        };

        return max($periods, 1);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Daily => 'Diaria',
            self::Weekly => 'Semanal',
            self::Biweekly => 'Quincenal',
            self::Monthly => 'Mensual',
        };
    }
}
