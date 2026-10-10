<?php

namespace App\Enums;

enum ContractHistoryField: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case AmountChanged = 'amount_changed';
    case DueDayChanged = 'due_day_changed';

    public function label(): string
    {
        return __('contracts.history_fields.'.$this->value);
    }
}
