<?php

namespace App\Filament\Cliente\Resources\Contratos\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContratosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha_inicio')
                    ->label('Desde')
                    ->date()
                    ->sortable(),
                TextColumn::make('fecha_fin')
                    ->label('Hasta')
                    ->date()
                    ->sortable(),
                TextColumn::make('estado')
                    ->badge(),
                TextColumn::make('presupuesto.total')
                    ->label('Monto mensual')
                    ->money('ARS'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
