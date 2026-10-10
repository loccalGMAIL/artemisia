<?php

namespace App\Filament\Staff\Resources\Pieces;

use App\Filament\Staff\Resources\Pieces\Pages\ListPieces;
use App\Filament\Staff\Resources\Pieces\Tables\PiecesTable;
use App\Models\Piece;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Pieces (spec 004). The rules live in the piece Actions and the policies; this resource only
 * lists them and triggers those Actions.
 */
class PieceResource extends Resource
{
    protected static ?string $model = Piece::class;

    protected static ?string $slug = 'pieces';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    public static function getModelLabel(): string
    {
        return __('pieces.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('pieces.plural');
    }

    public static function table(Table $table): Table
    {
        return PiecesTable::configure($table);
    }

    /** Pieces are created from a budget or as loose pieces, never from a plain form. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPieces::route('/'),
        ];
    }
}
