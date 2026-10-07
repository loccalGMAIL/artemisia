<?php

namespace App\Filament\Client\Pages\Auth;

use App\Enums\AccessPortal;
use App\Filament\Auth\PortalLogin;

class Login extends PortalLogin
{
    protected function portal(): AccessPortal
    {
        return AccessPortal::Client;
    }
}
