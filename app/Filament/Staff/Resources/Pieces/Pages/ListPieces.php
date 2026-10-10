<?php

namespace App\Filament\Staff\Resources\Pieces\Pages;

use App\Filament\Staff\Resources\Pieces\PieceActions;
use App\Filament\Staff\Resources\Pieces\PieceResource;
use Filament\Resources\Pages\ListRecords;

class ListPieces extends ListRecords
{
    protected static string $resource = PieceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PieceActions::createLoose(),
        ];
    }
}
