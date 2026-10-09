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

    public function label(): string
    {
        return __('pieces.statuses.'.$this->value);
    }
}
