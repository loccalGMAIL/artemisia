<?php

namespace App\Filament\Staff\Resources\Budgets\RelationManagers;

use App\Enums\BudgetHistoryField;
use App\Models\BudgetHistory;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only change history of the budget, oldest first (RF-34).
 */
class HistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'histories';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('budgets.relations.histories');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label(__('budgets.history.date'))->dateTime('d/m/Y H:i'),
                TextColumn::make('field')
                    ->label(__('budgets.history.fact'))
                    ->formatStateUsing(fn (BudgetHistoryField $state): string => $state->label()),
                TextColumn::make('old_value')
                    ->label(__('budgets.history.old_value'))
                    ->placeholder('—')
                    ->state(fn (BudgetHistory $record): ?string => self::describe($record->old_value)),
                TextColumn::make('new_value')
                    ->label(__('budgets.history.new_value'))
                    ->state(fn (BudgetHistory $record): ?string => self::describe($record->new_value)),
                TextColumn::make('author.name')->label(__('budgets.history.author')),
            ])
            ->paginated(false);
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    private static function describe(?array $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
