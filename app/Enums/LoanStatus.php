<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LoanStatus: string implements HasColor, HasLabel
{
    case Active = 'activo';
    case Paid = 'pagado';
    case Late = 'atrasado';
    case Cancelled = 'cancelado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Al día',
            self::Paid => 'Pagado',
            self::Late => 'En mora',
            self::Cancelled => 'Cancelado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'info',
            self::Paid => 'success',
            self::Late => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
