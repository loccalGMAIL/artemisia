<?php

namespace App\Enums;

enum ClientType: string
{
    case Individual = 'individual';
    case Company = 'company';

    public function label(): string
    {
        return __('clients.person_types.'.$this->value);
    }

    /** DNI for an individual, CUIT for a company (plan D-11). */
    public function documentLabel(): string
    {
        return __('clients.document_labels.'.$this->value);
    }
}
