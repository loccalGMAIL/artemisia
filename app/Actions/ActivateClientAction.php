<?php

namespace App\Actions;

use App\Enums\ClientStatus;

class ActivateClientAction extends ChangeClientStatusAction
{
    protected function target(): ClientStatus
    {
        return ClientStatus::Active;
    }
}
