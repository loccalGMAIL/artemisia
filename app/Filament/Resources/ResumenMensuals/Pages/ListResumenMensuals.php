<?php

namespace App\Filament\Resources\ResumenMensuals\Pages;

use App\Filament\Resources\ResumenMensuals\ResumenMensualResource;
use Filament\Resources\Pages\ListRecords;

class ListResumenMensuals extends ListRecords
{
    protected static string $resource = ResumenMensualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
