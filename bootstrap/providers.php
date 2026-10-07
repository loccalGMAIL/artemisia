<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\ClientPanelProvider;
use App\Providers\Filament\StaffPanelProvider;

return [
    AppServiceProvider::class,
    ClientPanelProvider::class,
    StaffPanelProvider::class,
];
