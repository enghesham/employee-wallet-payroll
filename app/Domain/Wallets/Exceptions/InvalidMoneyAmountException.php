<?php

namespace App\Domain\Wallets\Exceptions;

use InvalidArgumentException;

class InvalidMoneyAmountException extends InvalidArgumentException
{
    public static function notPositive(string $amount): self
    {
        return new self("Money amount must be positive. Received {$amount}.");
    }
}
