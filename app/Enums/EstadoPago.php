<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstadoPago: string implements HasColor, HasLabel
{
    case Pendiente = 'pendiente';
    case Parcial = 'parcial';
    case Pagado = 'pagado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Parcial => 'Parcial',
            self::Pagado => 'Pagado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pendiente => 'danger',
            self::Parcial => 'warning',
            self::Pagado => 'success',
        };
    }
}
