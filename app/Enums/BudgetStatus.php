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

    /**
     * The only moves allowed: draft to sent, sent to accepted or rejected, and back to sent
     * from either answer (RF-43, RF-45, RF-49). Nothing else.
     *
     * @return array<int, self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [self::Sent],
            self::Sent => [self::Accepted, self::Rejected],
            self::Accepted, self::Rejected => [self::Sent],
        };
    }

    public function canMoveTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function label(): string
    {
        return __('budgets.statuses.'.$this->value);
    }
}
