<?php

namespace App\Filament\Cliente\Resources\ResumenMensuals\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ResumenMensualsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('periodo', 'desc')
            ->columns([
                TextColumn::make('periodo')
                    ->sortable(),
                TextColumn::make('monto_base')
                    ->label('Base')
                    ->money('ARS'),
                TextColumn::make('monto_extras')
                    ->label('Extras')
                    ->money('ARS'),
                TextColumn::make('monto_total')
                    ->label('Total')
                    ->money('ARS')
                    ->sortable(),
                TextColumn::make('estado_pago')
                    ->label('Estado de pago')
                    ->badge(),
                TextColumn::make('fecha_pago')
                    ->label('Fecha de pago')
                    ->date()
                    ->placeholder('-'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
