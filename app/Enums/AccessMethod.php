<?php

namespace App\Enums;

enum AccessMethod: string
{
    case Password = 'password';
    case Google = 'google';

    public function label(): string
    {
        return __('logs.methods.'.$this->value);
    }
}
