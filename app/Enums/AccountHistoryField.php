<?php

namespace App\Enums;

enum AccountHistoryField: string
{
    case Created = 'created';
    case Activated = 'activated';
    case Deactivated = 'deactivated';
    case RoleChanged = 'role_changed';
    case NameChanged = 'name_changed';
    case EmailChanged = 'email_changed';
    case PasswordChanged = 'password_changed';

    public function label(): string
    {
        return __('logs.history_fields.'.$this->value);
    }
}
