<?php

namespace App\Exceptions;

use App\Enums\ContractStatus;
use RuntimeException;

/**
 * The contract is not in a state that allows the change asked for: only an active contract is
 * cancelled or modified, and only a cancelled one is reactivated (RF-21, RF-25, RF-28, RF-30,
 * RF-31).
 */
class InvalidContractTransitionException extends RuntimeException
{
    public static function transition(ContractStatus $from, ContractStatus $to): self
    {
        return new self(__('contracts.validation.invalid_transition', [
            'from' => mb_strtolower($from->label()),
            'to' => mb_strtolower($to->label()),
        ]));
    }

    public static function notActive(ContractStatus $status): self
    {
        return new self(__('contracts.validation.not_active', ['status' => mb_strtolower($status->label())]));
    }
}
