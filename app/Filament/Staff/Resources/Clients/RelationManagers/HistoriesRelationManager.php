<?php

namespace App\Filament\Staff\Resources\Clients\RelationManagers;

use App\Enums\ClientHistoryField;
use App\Models\ClientHistory;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only change history of the client, oldest first (RF-37).
 */
class HistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'histories';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('clients.relations.histories');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label(__('clients.fields.history_date'))->dateTime(),
                TextColumn::make('field')
                    ->label(__('clients.fields.history_field'))
                    ->formatStateUsing(fn (ClientHistoryField $state): string => $state->label()),
                TextColumn::make('old_value')
                    ->label(__('clients.fields.old_value'))
                    ->placeholder('—')
                    ->state(fn (ClientHistory $record): ?string => self::describe($record->old_value)),
                TextColumn::make('new_value')
                    ->label(__('clients.fields.new_value'))
                    ->state(fn (ClientHistory $record): ?string => self::describe($record->new_value)),
                TextColumn::make('author.name')->label(__('clients.fields.history_author')),
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
