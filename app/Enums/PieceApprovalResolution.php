<?php

namespace App\Enums;

enum PieceApprovalResolution: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('pieces.resolutions.'.$this->value);
    }
}
