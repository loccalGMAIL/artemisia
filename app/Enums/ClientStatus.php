<?php

namespace App\Enums;

enum ClientStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return __('clients.statuses.'.$this->value);
    }
}
