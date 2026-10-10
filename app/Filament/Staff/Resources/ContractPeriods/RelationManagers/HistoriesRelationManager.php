<?php

namespace App\Filament\Staff\Resources\ContractPeriods\RelationManagers;

use App\Enums\PeriodHistoryField;
use App\Models\PeriodHistory;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only change history of the period, oldest first (RF-86). The author is the system when
 * the change was automatic.
 */
class HistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'histories';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('contracts.relations.histories');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('author'))
            ->columns([
                TextColumn::make('created_at')->label(__('contracts.history.date'))->dateTime('d/m/Y H:i'),
                TextColumn::make('field')
                    ->label(__('contracts.history.fact'))
                    ->formatStateUsing(fn (PeriodHistoryField $state): string => $state->label()),
                TextColumn::make('old_value')
                    ->label(__('contracts.history.old_value'))
                    ->placeholder('—')
                    ->state(fn (PeriodHistory $record): ?string => self::describe($record->old_value)),
                TextColumn::make('new_value')
                    ->label(__('contracts.history.new_value'))
                    ->state(fn (PeriodHistory $record): ?string => self::describe($record->new_value)),
                TextColumn::make('author')
                    ->label(__('contracts.history.author'))
                    ->state(fn (PeriodHistory $record): string => $record->author_id === null ? __('contracts.system') : $record->author->name),
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
