<?php

namespace App\Filament\Staff\Resources\Contracts\RelationManagers;

use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only list of the periods of the contract, from the first month to the last (RF-84).
 */
class PeriodsRelationManager extends RelationManager
{
    protected static string $relationship = 'periods';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('contracts.relations.periods');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('month')
                    ->label(__('contracts.periods.month'))
                    ->date('m/Y'),
                TextColumn::make('due_date')
                    ->label(__('contracts.periods.due_date'))
                    ->date('d/m/Y'),
                TextColumn::make('total')
                    ->label(__('contracts.periods.total'))
                    ->formatStateUsing(fn (string $state): string => Money::display($state)),
                TextColumn::make('paid_amount')
                    ->label(__('contracts.periods.paid'))
                    ->formatStateUsing(fn (string $state): string => Money::display($state)),
            ])
            ->paginated(false);
    }
}
