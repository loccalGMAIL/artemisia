<?php

namespace App\Actions;

use App\Enums\ClientStatus;

class DeactivateClientAction extends ChangeClientStatusAction
{
    protected function target(): ClientStatus
    {
        return ClientStatus::Inactive;
    }
}
