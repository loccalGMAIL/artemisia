<?php

namespace App\Enums;

enum PieceHistoryField: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case AssigneeChanged = 'assignee_changed';
    case DueDateChanged = 'due_date_changed';

    public function label(): string
    {
        return __('pieces.history_fields.'.$this->value);
    }
}
