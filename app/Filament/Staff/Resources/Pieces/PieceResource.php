<?php

namespace App\Filament\Staff\Resources\Pieces;

use App\Filament\Staff\Resources\Pieces\Pages\ListPieces;
use App\Filament\Staff\Resources\Pieces\Pages\ViewPiece;
use App\Filament\Staff\Resources\Pieces\RelationManagers\HistoriesRelationManager;
use App\Filament\Staff\Resources\Pieces\RelationManagers\SubmissionsRelationManager;
use App\Filament\Staff\Resources\Pieces\Schemas\PieceInfolist;
use App\Filament\Staff\Resources\Pieces\Tables\PiecesTable;
use App\Models\Piece;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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

    public static function infolist(Schema $schema): Schema
    {
        return PieceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PiecesTable::configure($table);
    }

    /**
     * Discarded pieces stay reachable by their address (RF-27); the list hides them.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [
            SubmissionsRelationManager::class,
            HistoriesRelationManager::class,
        ];
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
            'view' => ViewPiece::route('/{record}'),
        ];
    }
}
