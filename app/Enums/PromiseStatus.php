<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PromiseStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Kept = 'kept';
    case Broken = 'broken';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Kept => 'Cumplida',
            self::Broken => 'Incumplida',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Kept => 'success',
            self::Broken => 'danger',
        };
    }
}
