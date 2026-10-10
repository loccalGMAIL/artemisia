<?php

namespace App\Filament\Staff\Resources\Contracts\RelationManagers;

use App\Enums\ContractHistoryField;
use App\Models\ContractHistory;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only change history of the contract, oldest first (RF-86). The author is the system when
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
                    ->formatStateUsing(fn (ContractHistoryField $state): string => $state->label()),
                TextColumn::make('old_value')
                    ->label(__('contracts.history.old_value'))
                    ->placeholder('—')
                    ->state(fn (ContractHistory $record): ?string => self::describe($record->old_value)),
                TextColumn::make('new_value')
                    ->label(__('contracts.history.new_value'))
                    ->state(fn (ContractHistory $record): ?string => self::describe($record->new_value)),
                TextColumn::make('author')
                    ->label(__('contracts.history.author'))
                    ->state(fn (ContractHistory $record): string => $record->author_id === null ? __('contracts.system') : $record->author->name),
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
