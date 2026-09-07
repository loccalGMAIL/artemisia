<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstadoProduccion: string implements HasColor, HasLabel
{
    case Pendiente = 'pendiente';
    case EnProgreso = 'en_progreso';
    case Entregado = 'entregado';
    case Cancelado = 'cancelado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnProgreso => 'En progreso',
            self::Entregado => 'Entregado',
            self::Cancelado => 'Cancelado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pendiente => 'gray',
            self::EnProgreso => 'info',
            self::Entregado => 'success',
            self::Cancelado => 'danger',
        };
    }
}
