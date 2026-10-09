<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InterestType: string implements HasLabel
{
    case Simple = 'simple';
    case Compound = 'compuesto';

    public function getLabel(): string
    {
        return match ($this) {
            self::Simple => 'Simple',
            self::Compound => 'Compuesto',
        };
    }
}
