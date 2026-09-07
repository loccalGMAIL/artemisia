<?php

namespace App\Filament\Cliente\Resources\Contratos\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContratoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('estado')
                    ->badge(),
                TextEntry::make('fecha_inicio')
                    ->label('Desde')
                    ->date(),
                TextEntry::make('fecha_fin')
                    ->label('Hasta')
                    ->date(),
                Section::make('Detalle del plan')
                    ->schema([
                        TextEntry::make('presupuesto.total')
                            ->label('Monto mensual')
                            ->money('ARS'),
                        RepeatableEntry::make('presupuesto.detalles')
                            ->label('Servicios incluidos')
                            ->columnSpanFull()
                            ->table([
                                TableColumn::make('Servicio'),
                                TableColumn::make('Cantidad'),
                                TableColumn::make('Precio unitario'),
                            ])
                            ->schema([
                                TextEntry::make('servicio.nombre')
                                    ->placeholder('-'),
                                TextEntry::make('cantidad'),
                                TextEntry::make('precio_unitario')
                                    ->money('ARS'),
                            ]),
                    ]),
            ]);
    }
}
