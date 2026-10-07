<?php

namespace App\Enums;

enum ClientHistoryField: string
{
    case Identification = 'identification';
    case Address = 'address';
    case Contacts = 'contacts';
    case Status = 'status';
    case Archived = 'archived';
    case AccountLink = 'account_link';

    public function label(): string
    {
        return __('clients.history_fields.'.$this->value);
    }
}
