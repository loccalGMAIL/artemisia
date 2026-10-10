<?php

namespace App\Enums;

enum PeriodHistoryField: string
{
    case Generated = 'generated';
    case ConceptAdded = 'concept_added';
    case ConceptRemoved = 'concept_removed';
    case InvoiceChanged = 'invoice_changed';
    case PaymentRegistered = 'payment_registered';
    case PaymentVoided = 'payment_voided';

    public function label(): string
    {
        return __('contracts.period_history_fields.'.$this->value);
    }
}
