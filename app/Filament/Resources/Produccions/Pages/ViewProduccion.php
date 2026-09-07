<?php

namespace App\Filament\Resources\Produccions\Pages;

use App\Filament\Resources\Produccions\ProduccionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduccion extends ViewRecord
{
    protected static string $resource = ProduccionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
