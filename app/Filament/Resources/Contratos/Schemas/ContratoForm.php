<?php

namespace App\Filament\Resources\Contratos\Schemas;

use App\Enums\EstadoContrato;
use App\Models\Contrato;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ContratoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('presupuesto_id')
                    ->label('Presupuesto')
                    ->formatStateUsing(fn (?Contrato $record): string => $record
                        ? "{$record->presupuesto->cliente->nombre} ({$record->presupuesto->fecha->format('d/m/Y')})"
                        : '-')
                    ->disabled()
                    ->dehydrated(false),
                DatePicker::make('fecha_inicio')
                    ->required(),
                DatePicker::make('fecha_fin')
                    ->required(),
                Select::make('estado')
                    ->options(EstadoContrato::class)
                    ->default('activo')
                    ->required(),
            ]);
    }
}
