<?php

namespace App\Filament\Resources\Produccions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProduccionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('presupuesto.cliente.nombre')
                    ->label('Cliente'),
                TextEntry::make('estado')
                    ->badge(),
                TextEntry::make('fecha_entrega')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('notas')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
