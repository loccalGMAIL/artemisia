<?php

namespace App\Enums;

enum BudgetModality: string
{
    case Single = 'single';
    case Monthly = 'monthly';

    public function label(): string
    {
        return __('budgets.modalities.'.$this->value);
    }
}
