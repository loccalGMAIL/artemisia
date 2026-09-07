<?php

namespace App\Filament\Resources\Contratos\Schemas;

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
                    ->date(),
                TextEntry::make('fecha_fin')
                    ->date(),
                Section::make('Presupuesto origen')
                    ->schema([
                        TextEntry::make('presupuesto.cliente.nombre')
                            ->label('Cliente'),
                        TextEntry::make('presupuesto.fecha')
                            ->date(),
                        TextEntry::make('presupuesto.total')
                            ->money('ARS'),
                        RepeatableEntry::make('presupuesto.detalles')
                            ->label('Líneas')
                            ->columnSpanFull()
                            ->table([
                                TableColumn::make('Servicio'),
                                TableColumn::make('Descripción'),
                                TableColumn::make('Cantidad'),
                                TableColumn::make('Precio unitario'),
                                TableColumn::make('Subtotal'),
                            ])
                            ->schema([
                                TextEntry::make('servicio.nombre')
                                    ->placeholder('-'),
                                TextEntry::make('descripcion_libre')
                                    ->placeholder('-'),
                                TextEntry::make('cantidad'),
                                TextEntry::make('precio_unitario')
                                    ->money('ARS'),
                                TextEntry::make('subtotal')
                                    ->money('ARS'),
                            ]),
                    ])
                    ->columns(3),
            ]);
    }
}
