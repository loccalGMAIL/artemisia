<?php

namespace App\Enums;

enum BudgetStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    /** Items, discount and header can change only while draft or sent (RF-16, RF-48). */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Sent;
    }

    public function label(): string
    {
        return __('budgets.statuses.'.$this->value);
    }
}
