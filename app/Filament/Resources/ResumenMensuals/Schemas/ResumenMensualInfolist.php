<?php

namespace App\Filament\Resources\ResumenMensuals\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ResumenMensualInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('contrato.presupuesto.cliente.nombre')
                    ->label('Cliente'),
                TextEntry::make('periodo'),
                TextEntry::make('monto_base')
                    ->money('ARS'),
                TextEntry::make('monto_extras')
                    ->money('ARS'),
                TextEntry::make('monto_total')
                    ->money('ARS'),
                TextEntry::make('estado_pago')
                    ->badge(),
                TextEntry::make('fecha_pago')
                    ->date()
                    ->placeholder('-'),
                IconEntry::make('enviado')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
