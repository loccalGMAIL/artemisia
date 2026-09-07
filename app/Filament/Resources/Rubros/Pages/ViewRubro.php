<?php

namespace App\Filament\Resources\Rubros\Pages;

use App\Filament\Resources\Rubros\RubroResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRubro extends ViewRecord
{
    protected static string $resource = RubroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
