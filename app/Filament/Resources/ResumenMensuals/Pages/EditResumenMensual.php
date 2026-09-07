<?php

namespace App\Filament\Resources\ResumenMensuals\Pages;

use App\Filament\Resources\ResumenMensuals\ResumenMensualResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditResumenMensual extends EditRecord
{
    protected static string $resource = ResumenMensualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
