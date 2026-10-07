<?php

namespace App\Enums;

enum AccessRejection: string
{
    case InvalidCredentials = 'invalid_credentials';
    case AccountNotEnabled = 'account_not_enabled';
    case AccountInactive = 'account_inactive';
    case WrongPortal = 'wrong_portal';

    public function message(): string
    {
        return __('auth.rejection.'.$this->value);
    }
}
