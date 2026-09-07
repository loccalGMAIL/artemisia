<?php

namespace App\Filament\Cliente\Resources\Produccions\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProduccionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('estado')
                    ->badge(),
                TextColumn::make('fecha_entrega')
                    ->label('Fecha de entrega')
                    ->date()
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
