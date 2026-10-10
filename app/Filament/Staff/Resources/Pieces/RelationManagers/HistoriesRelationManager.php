<?php

namespace App\Filament\Staff\Resources\Pieces\RelationManagers;

use App\Enums\PieceHistoryField;
use App\Models\PieceHistory;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only change history of the piece, oldest first (RF-39).
 */
class HistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'histories';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('pieces.relations.histories');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label(__('pieces.history.date'))->dateTime('d/m/Y H:i'),
                TextColumn::make('field')
                    ->label(__('pieces.history.fact'))
                    ->formatStateUsing(fn (PieceHistoryField $state): string => $state->label()),
                TextColumn::make('old_value')
                    ->label(__('pieces.history.old_value'))
                    ->placeholder('—')
                    ->state(fn (PieceHistory $record): ?string => self::describe($record->old_value)),
                TextColumn::make('new_value')
                    ->label(__('pieces.history.new_value'))
                    ->state(fn (PieceHistory $record): ?string => self::describe($record->new_value)),
                TextColumn::make('author.name')->label(__('pieces.history.author')),
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
