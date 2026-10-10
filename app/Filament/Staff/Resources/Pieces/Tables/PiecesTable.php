<?php

namespace App\Filament\Staff\Resources\Pieces\Tables;

use App\Enums\PieceStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PiecesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('pieces.fields.name')),
                TextColumn::make('status')
                    ->label(__('pieces.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (PieceStatus $state): string => $state->label()),
            ])
            ->defaultSort('id', 'desc');
    }
}
