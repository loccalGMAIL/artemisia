<?php

namespace App\Filament\Staff\Resources\AccessLogs\Pages;

use App\Filament\Staff\Resources\AccessLogs\AccessLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAccessLogs extends ListRecords
{
    protected static string $resource = AccessLogResource::class;
}
