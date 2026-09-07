<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UnidadServicio: string implements HasLabel
{
    case Unidad = 'unidad';
    case Hora = 'hora';
    case Segundo = 'segundo';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unidad => 'Unidad',
            self::Hora => 'Hora',
            self::Segundo => 'Segundo',
        };
    }
}
