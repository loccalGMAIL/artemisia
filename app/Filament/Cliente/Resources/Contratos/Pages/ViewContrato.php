<?php

namespace App\Filament\Cliente\Resources\Contratos\Pages;

use App\Filament\Cliente\Resources\Contratos\ContratoResource;
use Filament\Resources\Pages\ViewRecord;

class ViewContrato extends ViewRecord
{
    protected static string $resource = ContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
