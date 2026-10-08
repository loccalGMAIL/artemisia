<?php

namespace App\Filament\Staff\Resources\Services\Tables;

use App\Filament\Staff\Resources\Services\ServiceActions;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('budgets.services.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label(__('budgets.services.fields.category')),
                TextColumn::make('list_price')
                    ->label(__('budgets.services.fields.list_price'))
                    ->formatStateUsing(fn (string $state): string => '$ '.number_format((float) $state, 2, ',', '.'))
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('budgets.services.fields.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                ServiceActions::toggleActive(),
            ]);
    }
}
