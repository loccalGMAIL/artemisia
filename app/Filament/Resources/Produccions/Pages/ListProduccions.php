<?php

namespace App\Filament\Resources\Produccions\Pages;

use App\Filament\Resources\Produccions\ProduccionResource;
use Filament\Resources\Pages\ListRecords;

class ListProduccions extends ListRecords
{
    protected static string $resource = ProduccionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
