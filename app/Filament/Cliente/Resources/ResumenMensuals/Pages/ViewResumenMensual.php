<?php

namespace App\Filament\Cliente\Resources\ResumenMensuals\Pages;

use App\Filament\Cliente\Resources\ResumenMensuals\ResumenMensualResource;
use Filament\Resources\Pages\ViewRecord;

class ViewResumenMensual extends ViewRecord
{
    protected static string $resource = ResumenMensualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
