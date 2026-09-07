<?php

namespace App\Filament\Resources\ResumenMensuals\Tables;

use App\Enums\EstadoPago;
use App\Models\ResumenMensual;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ResumenMensualsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contrato.presupuesto.cliente.nombre')
                    ->label('Cliente')
                    ->searchable(),
                TextColumn::make('periodo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('monto_base')
                    ->money('ARS')
                    ->sortable(),
                TextColumn::make('monto_extras')
                    ->money('ARS')
                    ->sortable(),
                TextColumn::make('monto_total')
                    ->money('ARS')
                    ->sortable(),
                TextColumn::make('estado_pago')
                    ->badge(),
                TextColumn::make('fecha_pago')
                    ->date()
                    ->sortable(),
                IconColumn::make('enviado')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('periodo')
                    ->options(fn (): array => ResumenMensual::query()
                        ->distinct()
                        ->orderByDesc('periodo')
                        ->pluck('periodo', 'periodo')
                        ->all()),
                SelectFilter::make('estado_pago')
                    ->label('Estado de pago')
                    ->options(EstadoPago::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
