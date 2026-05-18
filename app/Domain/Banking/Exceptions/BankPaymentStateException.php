<?php

namespace App\Domain\Banking\Exceptions;

use RuntimeException;

class BankPaymentStateException extends RuntimeException
{
    public static function conflictingFinalState(string $current, string $requested): self
    {
        return new self("Bank payment is already {$current}; cannot mark it as {$requested}.");
    }
}
