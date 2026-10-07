<?php

namespace App\Filament\Staff\Pages\Auth;

use App\Enums\AccessPortal;
use App\Filament\Auth\PortalLogin;

class Login extends PortalLogin
{
    protected function portal(): AccessPortal
    {
        return AccessPortal::Staff;
    }
}
