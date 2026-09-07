<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\ClientePanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    ClientePanelProvider::class,
];
