<?php

namespace App\Filament\Cliente\Resources\Produccions\Pages;

use App\Filament\Cliente\Resources\Produccions\ProduccionResource;
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
