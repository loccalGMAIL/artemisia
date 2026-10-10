<?php

namespace App\Enums;

enum ConceptType: string
{
    case Extra = 'extra';
    case Discount = 'discount';

    public function label(): string
    {
        return __('contracts.concept_types.'.$this->value);
    }
}
