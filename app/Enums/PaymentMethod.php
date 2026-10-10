<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Other = 'other';

    public function label(): string
    {
        return __('contracts.payment_methods.'.$this->value);
    }
}
