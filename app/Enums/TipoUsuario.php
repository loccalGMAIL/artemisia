<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TipoUsuario: string implements HasColor, HasLabel
{
    case Staff = 'staff';
    case Cliente = 'cliente';

    public function getLabel(): string
    {
        return match ($this) {
            self::Staff => 'Staff',
            self::Cliente => 'Cliente',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Staff => 'primary',
            self::Cliente => 'info',
        };
    }
}
