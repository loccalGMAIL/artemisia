<?php

namespace App\Exceptions;

use App\Enums\ContractStatus;
use RuntimeException;

/**
 * The contract is not in a state that allows the change asked for: only an active contract is
 * cancelled, and only a cancelled one is reactivated (RF-21, RF-25, RF-28).
 */
class InvalidContractTransitionException extends RuntimeException
{
    public function __construct(ContractStatus $from, ContractStatus $to)
    {
        parent::__construct(__('contracts.validation.invalid_transition', [
            'from' => mb_strtolower($from->label()),
            'to' => mb_strtolower($to->label()),
        ]));
    }
}
