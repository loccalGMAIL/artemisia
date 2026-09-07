<?php

namespace App\Filament\Resources\Roles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->badge()
                    ->searchable(),
                TextColumn::make('permissions_count')
                    ->label('Permisos')
                    ->counts('permissions'),
                TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->counts('users'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Los roles `staff`/`cliente` no se pueden borrar (ver
                    // RolePolicy::delete()); authorizeIndividualRecords hace
                    // que esa protección se respete también en el borrado
                    // masivo, en vez de decidir todo por `deleteAny()`.
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }
}
