<?php

namespace App\Filament\Cliente\Resources\ResumenMensuals\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ResumenMensualInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('periodo'),
                TextEntry::make('estado_pago')
                    ->label('Estado de pago')
                    ->badge(),
                TextEntry::make('monto_base')
                    ->label('Base')
                    ->money('ARS'),
                TextEntry::make('monto_extras')
                    ->label('Extras')
                    ->money('ARS'),
                TextEntry::make('monto_total')
                    ->label('Total')
                    ->money('ARS'),
                TextEntry::make('fecha_pago')
                    ->label('Fecha de pago')
                    ->date()
                    ->placeholder('-'),
                RepeatableEntry::make('detalles')
                    ->label('Detalle')
                    ->columnSpanFull()
                    ->table([
                        TableColumn::make('Servicio'),
                        TableColumn::make('Cantidad'),
                        TableColumn::make('Precio unitario'),
                        TableColumn::make('Subtotal'),
                        TableColumn::make('Extra'),
                    ])
                    ->schema([
                        TextEntry::make('servicio.nombre')
                            ->placeholder('-'),
                        TextEntry::make('cantidad'),
                        TextEntry::make('precio_unitario')
                            ->money('ARS'),
                        TextEntry::make('subtotal')
                            ->money('ARS'),
                        TextEntry::make('es_extra')
                            ->label('Extra')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Sí' : 'No')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),
                    ]),
            ]);
    }
}
