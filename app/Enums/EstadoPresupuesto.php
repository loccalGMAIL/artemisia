<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstadoPresupuesto: string implements HasColor, HasLabel
{
    case Borrador = 'borrador';
    case Enviado = 'enviado';
    case Confirmado = 'confirmado';
    case Rechazado = 'rechazado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Enviado => 'Enviado',
            self::Confirmado => 'Confirmado',
            self::Rechazado => 'Rechazado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Borrador => 'gray',
            self::Enviado => 'info',
            self::Confirmado => 'success',
            self::Rechazado => 'danger',
        };
    }
}
