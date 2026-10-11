<?php

namespace App\Filament\Staff\Resources\Contracts\Pages;

use App\Filament\Staff\Resources\Contracts\ContractActions;
use App\Filament\Staff\Resources\Contracts\ContractResource;
use App\Models\Contract;
use Filament\Resources\Pages\ViewRecord;

/**
 * @method Contract getRecord()
 */
class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ContractActions::updateAmount(),
            ContractActions::updateDueDay(),
            ContractActions::cancel(),
            ContractActions::reactivate(),
        ];
    }
}
