<?php

namespace App\Filament\Staff\Resources\Pieces\Schemas;

use App\Enums\PieceStatus;
use App\Models\Piece;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The record card of a piece. Submissions and history are the relation managers below it.
 */
class PieceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('pieces.sections.data'))->schema([
                TextEntry::make('name')->label(__('pieces.fields.name')),
                TextEntry::make('status')
                    ->label(__('pieces.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (PieceStatus $state): string => $state->label()),
                TextEntry::make('description')
                    ->label(__('pieces.fields.description'))
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('budget.title')
                    ->label(__('pieces.fields.budget'))
                    ->formatStateUsing(fn (string $state, Piece $record): string => "#{$record->budget_id} · {$state}"),
                TextEntry::make('budget.client.display_name')->label(__('pieces.fields.client')),
                TextEntry::make('category.name')->label(__('pieces.fields.category')),
                TextEntry::make('assignee.name')
                    ->label(__('pieces.fields.assignee'))
                    ->placeholder(__('pieces.unassigned')),
                TextEntry::make('due_date')
                    ->label(__('pieces.fields.due_date'))
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->helperText(fn (Piece $record): ?string => $record->isOverdue() ? __('pieces.overdue') : null),
                TextEntry::make('creator.name')->label(__('pieces.fields.author')),
            ])->columns(2),
        ]);
    }
}
