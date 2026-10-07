<?php

namespace App\Filament\Staff\Resources\AccountHistories\Pages;

use App\Filament\Staff\Resources\AccountHistories\AccountHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListAccountHistories extends ListRecords
{
    protected static string $resource = AccountHistoryResource::class;
}
