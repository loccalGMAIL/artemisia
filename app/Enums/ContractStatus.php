<?php

namespace App\Enums;

enum ContractStatus: string
{
    case Active = 'active';
    case Finished = 'finished';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('contracts.statuses.'.$this->value);
    }
}
