<?php

namespace App\Filament\Staff\Resources\Services\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only price history of the service, oldest first (RF-10).
 */
class PriceHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'priceHistories';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('budgets.services.price_histories.title');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label(__('budgets.services.price_histories.date'))->dateTime(),
                TextColumn::make('old_price')
                    ->label(__('budgets.services.price_histories.old_price'))
                    ->placeholder('—')
                    ->formatStateUsing(fn (string $state): string => '$ '.number_format((float) $state, 2, ',', '.')),
                TextColumn::make('new_price')
                    ->label(__('budgets.services.price_histories.new_price'))
                    ->formatStateUsing(fn (string $state): string => '$ '.number_format((float) $state, 2, ',', '.')),
                TextColumn::make('author.name')->label(__('budgets.services.price_histories.author')),
            ])
            ->paginated(false);
    }
}
