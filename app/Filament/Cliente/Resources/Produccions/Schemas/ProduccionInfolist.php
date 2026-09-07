<?php

namespace App\Filament\Cliente\Resources\Produccions\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProduccionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('estado')
                    ->badge(),
                TextEntry::make('fecha_entrega')
                    ->label('Fecha de entrega')
                    ->date()
                    ->placeholder('A confirmar'),
                Section::make('Trabajo solicitado')
                    ->schema([
                        RepeatableEntry::make('presupuesto.detalles')
                            ->label('Piezas')
                            ->columnSpanFull()
                            ->table([
                                TableColumn::make('Servicio'),
                                TableColumn::make('Descripción'),
                                TableColumn::make('Cantidad'),
                            ])
                            ->schema([
                                TextEntry::make('servicio.nombre')
                                    ->placeholder('-'),
                                TextEntry::make('descripcion_libre')
                                    ->placeholder('-'),
                                TextEntry::make('cantidad'),
                            ]),
                    ]),
            ]);
    }
}
