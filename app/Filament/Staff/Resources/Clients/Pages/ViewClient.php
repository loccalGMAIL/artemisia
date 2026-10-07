<?php

namespace App\Filament\Staff\Resources\Clients\Pages;

use App\Filament\Staff\Resources\Clients\ClientActions;
use App\Filament\Staff\Resources\Clients\ClientResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ...ClientActions::state(),
        ];
    }
}
