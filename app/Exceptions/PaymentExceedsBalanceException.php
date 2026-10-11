<?php

namespace App\Exceptions;

use App\Support\Money;
use RuntimeException;

/**
 * The amount of a payment is higher than what is still owed on the period, as it stands when
 * the payment is confirmed (RF-64, RF-65).
 */
class PaymentExceedsBalanceException extends RuntimeException
{
    public function __construct(string $balance)
    {
        parent::__construct(__('contracts.validation.payment_exceeds_balance', ['balance' => Money::display($balance)]));
    }
}
