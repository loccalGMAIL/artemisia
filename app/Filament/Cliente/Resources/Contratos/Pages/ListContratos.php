<?php

namespace App\Filament\Cliente\Resources\Contratos\Pages;

use App\Filament\Cliente\Resources\Contratos\ContratoResource;
use Filament\Resources\Pages\ListRecords;

class ListContratos extends ListRecords
{
    protected static string $resource = ContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
