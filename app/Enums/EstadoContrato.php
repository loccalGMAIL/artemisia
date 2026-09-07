<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstadoContrato: string implements HasColor, HasLabel
{
    case Activo = 'activo';
    case Finalizado = 'finalizado';
    case Cancelado = 'cancelado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Finalizado => 'Finalizado',
            self::Cancelado => 'Cancelado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Activo => 'success',
            self::Finalizado => 'gray',
            self::Cancelado => 'danger',
        };
    }
}
