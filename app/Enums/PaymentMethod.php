<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'efectivo';
    case Nequi = 'nequi';
    case Daviplata = 'daviplata';
    case Transfer = 'transferencia';
    case Other = 'otro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::Nequi => 'Nequi',
            self::Daviplata => 'Daviplata',
            self::Transfer => 'Transferencia',
            self::Other => 'Otro',
        };
    }
}
