<?php

namespace App\Enums;

enum BudgetDiscountType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    public function label(): string
    {
        return __('budgets.discount_types.'.$this->value);
    }
}
