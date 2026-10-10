<?php

namespace App\Enums;

enum PieceStatus: string
{
    case Pending = 'pending';
    case InProduction = 'in_production';
    case InReview = 'in_review';
    case ClientApproval = 'client_approval';
    case Approved = 'approved';
    case Delivered = 'delivered';

    /**
     * The only moves allowed: forward through the production path, and back to production when
     * the client rejects a piece in approval (RF-20 to RF-24, RF-34). A delivered piece is final.
     *
     * @return array<int, self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending => [self::InProduction],
            self::InProduction => [self::InReview],
            self::InReview => [self::ClientApproval],
            self::ClientApproval => [self::Approved, self::InProduction],
            self::Approved => [self::Delivered],
            self::Delivered => [],
        };
    }

    /**
     * The states a client sees in its portal: in its approval, approved or delivered. What is
     * still being produced stays internal (RF-45, RF-46).
     *
     * @return array<int, self>
     */
    public static function visibleToClient(): array
    {
        return [self::ClientApproval, self::Approved, self::Delivered];
    }

    public function canMoveTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function label(): string
    {
        return __('pieces.statuses.'.$this->value);
    }
}
