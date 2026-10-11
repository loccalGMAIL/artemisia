<?php

namespace App\Filament\Staff\Resources\Clients\Pages;

use App\Filament\Staff\Resources\Clients\ClientActions;
use App\Filament\Staff\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @method Client getRecord()
 */
class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    /** The card is titled with the name of the client, not with "View client". */
    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->display_name;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ...ClientActions::state(),
        ];
    }
}
