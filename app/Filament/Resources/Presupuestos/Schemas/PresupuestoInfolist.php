<?php

namespace App\Filament\Resources\Presupuestos\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PresupuestoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('cliente.nombre')
                    ->label('Cliente'),
                TextEntry::make('fecha')
                    ->date(),
                TextEntry::make('estado')
                    ->badge(),
                TextEntry::make('total')
                    ->money('ARS'),
                TextEntry::make('notas')
                    ->placeholder('-')
                    ->columnSpanFull(),
                RepeatableEntry::make('detalles')
                    ->label('Detalle')
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
            ]);
    }
}
