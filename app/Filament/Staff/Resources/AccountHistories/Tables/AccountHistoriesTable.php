<?php

namespace App\Filament\Staff\Resources\AccountHistories\Tables;

use App\Enums\AccountHistoryField;
use App\Models\AccountHistory;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AccountHistoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('logs.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('logs.fields.user'))
                    ->searchable(),
                TextColumn::make('field')
                    ->label(__('logs.fields.field'))
                    ->formatStateUsing(fn (AccountHistoryField $state): string => $state->label()),
                TextColumn::make('old_value')
                    ->label(__('logs.fields.old_value'))
                    ->placeholder('—')
                    ->state(fn (AccountHistory $record): ?string => self::describe($record->old_value)),
                TextColumn::make('new_value')
                    ->label(__('logs.fields.new_value'))
                    ->state(fn (AccountHistory $record): ?string => self::describe($record->new_value)),
                TextColumn::make('author.name')
                    ->label(__('logs.fields.author')),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    private static function describe(?array $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
