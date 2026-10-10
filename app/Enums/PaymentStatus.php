<?php

namespace App\Enums;

/**
 * The collection status of a period. It is never stored: it is worked out from the period's
 * total and what has been paid (RF-70).
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';

    public function label(): string
    {
        return __('contracts.payment_statuses.'.$this->value);
    }
}
