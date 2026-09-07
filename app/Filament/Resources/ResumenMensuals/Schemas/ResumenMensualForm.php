<?php

namespace App\Filament\Resources\ResumenMensuals\Schemas;

use App\Enums\EstadoPago;
use App\Models\ResumenMensual;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ResumenMensualForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('contrato_id')
                    ->label('Contrato')
                    ->formatStateUsing(fn (?ResumenMensual $record): string => $record
                        ? $record->contrato->presupuesto->cliente->nombre
                        : '-')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('periodo')
                    ->disabled(),
                TextInput::make('monto_base')
                    ->label('Monto base')
                    ->numeric()
                    ->prefix('$')
                    ->disabled(),
                TextInput::make('monto_extras')
                    ->label('Monto extras')
                    ->numeric()
                    ->prefix('$')
                    ->disabled(),
                TextInput::make('monto_total')
                    ->label('Monto total')
                    ->numeric()
                    ->prefix('$')
                    ->disabled(),
                Select::make('estado_pago')
                    ->label('Estado de pago')
                    ->options(EstadoPago::class)
                    ->required(),
                DatePicker::make('fecha_pago')
                    ->label('Fecha de pago'),
                Toggle::make('enviado'),
                Repeater::make('detalles')
                    ->relationship()
                    ->label('Detalle')
                    ->columnSpanFull()
                    ->addActionLabel('Agregar línea')
                    ->schema([
                        Select::make('servicio_id')
                            ->label('Servicio')
                            ->relationship('servicio', 'nombre')
                            ->searchable()
                            ->preload()
                            ->columnSpan(2),
                        TextInput::make('descripcion_libre')
                            ->label('Descripción (ítem libre)')
                            ->columnSpan(2),
                        TextInput::make('cantidad')
                            ->numeric()
                            ->default(1)
                            ->required(),
                        TextInput::make('precio_unitario')
                            ->label('Precio unitario')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Toggle::make('es_extra')
                            ->label('Extra')
                            ->helperText('Activá esto si la línea surgió fuera del plan pactado.'),
                    ])
                    ->columns(6)
                    ->defaultItems(0),
            ]);
    }
}
