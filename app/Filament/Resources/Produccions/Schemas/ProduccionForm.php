<?php

namespace App\Filament\Resources\Produccions\Schemas;

use App\Enums\EstadoProduccion;
use App\Models\Produccion;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProduccionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('presupuesto_id')
                    ->label('Presupuesto')
                    ->formatStateUsing(fn (?Produccion $record): string => $record
                        ? "{$record->presupuesto->cliente->nombre} ({$record->presupuesto->fecha->format('d/m/Y')})"
                        : '-')
                    ->disabled()
                    ->dehydrated(false),
                Select::make('estado')
                    ->options(EstadoProduccion::class)
                    ->default('pendiente')
                    ->required(),
                DatePicker::make('fecha_entrega'),
                Textarea::make('notas')
                    ->columnSpanFull(),
            ]);
    }
}
