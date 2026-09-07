<?php

namespace App\Filament\Resources\ResumenMensuals\Pages;

use App\Filament\Resources\ResumenMensuals\ResumenMensualResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewResumenMensual extends ViewRecord
{
    protected static string $resource = ResumenMensualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
