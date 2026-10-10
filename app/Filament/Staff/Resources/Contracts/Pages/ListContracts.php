<?php

namespace App\Filament\Staff\Resources\Contracts\Pages;

use App\Filament\Staff\Resources\Contracts\ContractResource;
use Filament\Resources\Pages\ListRecords;

class ListContracts extends ListRecords
{
    protected static string $resource = ContractResource::class;
}
