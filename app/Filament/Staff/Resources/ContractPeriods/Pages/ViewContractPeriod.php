<?php

namespace App\Filament\Staff\Resources\ContractPeriods\Pages;

use App\Filament\Staff\Resources\ContractPeriods\ContractPeriodResource;
use App\Filament\Staff\Resources\ContractPeriods\PeriodActions;
use App\Models\ContractPeriod;
use Filament\Resources\Pages\ViewRecord;

/**
 * @method ContractPeriod getRecord()
 */
class ViewContractPeriod extends ViewRecord
{
    protected static string $resource = ContractPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PeriodActions::addExtra(),
            PeriodActions::addDiscount(),
            PeriodActions::setInvoice(),
        ];
    }
}
