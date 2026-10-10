<?php

namespace App\Filament\Staff\Resources\ContractPeriods\Pages;

use App\Filament\Staff\Resources\ContractPeriods\ContractPeriodResource;
use App\Models\ContractPeriod;
use Filament\Resources\Pages\ViewRecord;

/**
 * @method ContractPeriod getRecord()
 */
class ViewContractPeriod extends ViewRecord
{
    protected static string $resource = ContractPeriodResource::class;
}
