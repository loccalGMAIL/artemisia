<?php

namespace App\Enums;

enum BudgetHistoryField: string
{
    case ItemAdded = 'item_added';
    case ItemUpdated = 'item_updated';
    case ItemRemoved = 'item_removed';
    case DiscountChanged = 'discount_changed';
    case StatusChanged = 'status_changed';
    case HeaderChanged = 'header_changed';

    public function label(): string
    {
        return __('budgets.history_fields.'.$this->value);
    }
}
